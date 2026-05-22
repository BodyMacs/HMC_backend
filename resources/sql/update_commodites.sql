-- Requête pour mettre à jour les biens existants avec des commodités par défaut
-- Attention : Cette requête est compatible avec MySQL (utilisé par défaut dans Laravel)

UPDATE properties 
SET commodites = '["École", "Hôpital", "Supermarché", "Restaurant", "Pharmacie"]' 
WHERE commodites IS NULL OR commodites = '[]';

-- Si vous voulez varier par ville (exemple pour Douala)
UPDATE properties 
SET commodites = '["Marché central", "École", "Clinique", "Station service"]' 
WHERE city = 'Douala' AND (commodites IS NULL OR commodites = '[]');

-- Si vous voulez varier par ville (exemple pour Yaoundé)
UPDATE properties 
SET commodites = '["Université", "Boulangerie", "Hôpital", "Banque"]' 
WHERE city = 'Yaoundé' AND (commodites IS NULL OR commodites = '[]');

UPDATE properties 
SET is_furnished = 1 
WHERE title LIKE '%meublé%' OR description LIKE '%meublé%';
