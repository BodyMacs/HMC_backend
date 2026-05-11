<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $model = 'gemini-1.5-flash'; // gemini-flash-latest pointe vers 1.5 flash par défaut
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY');
    }

    /**
     * Send a prompt to Gemini and get a response.
     *
     * @param string $userMessage
     * @param array $history Previous messages [role => 'user'|'model', content => '...']
     * @return string
     * @throws \Exception
     */
    public function chat(string $userMessage, array $history = []): string
    {
        $systemInstruction = $this->getSystemPrompt();

        $contents = [];
        // Add history
        foreach ($history as $msg) {
                $contents[] = [
                    'role' => ($msg['role'] === 'user') ? 'user' : 'model',
                    'parts' => [
                        ['text' => $msg['content']]
                    ]
                ];
        }
        
        // Add current message
        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $userMessage]
            ]
        ];

        try {
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->withHeaders([
                    'X-goog-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent', [
                'contents' => $contents,
                'system_instruction' => [
                    'parts' => [
                        ['text' => $systemInstruction]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 1024,
                ]
            ]);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->body();
                Log::error("Gemini API Error ($status): " . $body);
                
                if ($status === 429) {
                    throw new \Exception("Limite de requêtes atteinte (Quota Exceeded). Veuillez réessayer dans une minute.");
                }

                $data = $response->json();
                if (isset($data['error']['message'])) {
                    throw new \Exception("Erreur Gemini : " . $data['error']['message']);
                }

                throw new \Exception('Désolé, une erreur est survenue lors de la communication avec l\'assistant IA.');
            }

            $data = $response->json();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Je n\'ai pas pu générer de réponse.';

        } catch (\Exception $e) {
            Log::error('Gemini Service Exception: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getSystemPrompt(): string
    {
        return <<<EOD
Tu es l'Assistant Intelligent de la plateforme "Home Cameroon". 
Ton but est d'accompagner les utilisateurs camerounais dans leurs démarches sur le site de manière sécurisée et accueillante.

=== PRÉSENTATION GÉNÉRALE ===
Home Cameroon est la plateforme de référence au Cameroun pour l'immobilier, la marketplace d'objets de maison, et les services de proximité.
Slogan : "L'immobilier et la maison en toute sécurité."

=== TES CONNAISSANCES CLÉS ===
1. IMMOBILIER : Location et vente de logements (Studios, Appartements, Villas).
   - Visites : Gérées par des agents HMC. Frais de visite : 10 000 FCFA pour garantir le sérieux.
2. MARKETPLACE : Achat/Vente d'articles pour la maison.
   - LE SÉQUESTRE : Home Cameroon garantit la sécurité. L'acheteur paie la plateforme. L'argent est bloqué (séquestre) jusqu'à ce que l'acheteur confirme la réception. C'est la garantie "Anti-Arnaque".
3. PRESTATAIRES : Experts pour travaux (plomberie, électricité, etc.). Assistance juridique et aide au recouvrement disponible pour les professionnels.
4. PAIEMENTS : Via NotchPay (Orange Money, MTN MoMo).

=== CONTACT & SUPPORT ===
- Téléphone/WhatsApp : +237 678 51 46 45
- Email : support@hmc.cm

=== TON IDENTITÉ & RÈGLES ===
- Ton : Chaleureux (ex: "Bienvenue chez nous"), professionnel, et poli. 
- Langue : Français.
- Sois concis : Ne fais pas de longs paragraphes. Va à l'essentiel.
- Si tu ne sais pas : Dirige vers le contact support ci-dessus ou propose d'ouvrir un ticket.

=== NAVIGATION ===
Suggère ces liens si besoin :
- Marketplace : marketplace
- Annonces Immobilières : annonces
- Devenir Vendeur : marketplace/vendeur
- Mes Commandes : marketplace/orders

Réponds avec empathie et efficacité.
EOD;
    }
}
