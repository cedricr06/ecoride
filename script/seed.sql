INSERT INTO utilisateurs (pseudo,email,mot_de_passe_hash,role,credits) VALUES
('lina','lina@example.com','$2y$10$Z2pZtS1mC2r2j3b6pV7yMe4m.qr5sPz1xgPp2oRr8tqzJm2Xv7bG6','utilisateur',40),
('alex','alex@example.com','$2y$10$Z2pZtS1mC2r2j3b6pV7yMe4m.qr5sPz1xgPp2oRr8tqzJm2Xv7bG6','utilisateur',60),
('sara','sara@example.com','$2y$10$Z2pZtS1mC2r2j3b6pV7yMe4m.qr5sPz1xgPp2oRr8tqzJm2Xv7bG6','employe',20),
('admin','admin@example.com','$2y$10$Z2pZtS1mC2r2j3b6pV7yMe4m.qr5sPz1xgPp2oRr8tqzJm2Xv7bG6','administrateur',0);

INSERT INTO vehicules (utilisateur_id,marque,modele,couleur,immatriculation,energie,places,date_premiere_immatriculation) VALUES
(1,'Renault','ZOE','blanc','AA-123-ZE','electrique',4,'2021-03-01'),
(2,'Peugeot','308','bleu','BB-456-GT','essence',4,'2018-06-12');

INSERT INTO voyages (chauffeur_id,vehicule_id,ville_depart,ville_arrivee,date_depart,date_arrivee,prix,places_disponibles,statut,ecologique) VALUES
(1,1,'Nice','Marseille','2025-09-01 08:00:00','2025-09-01 10:30:00',15.00,3,'ouvert',1),
(2,2,'Nice','Cannes','2025-09-02 09:00:00','2025-09-02 09:45:00',6.00,2,'ouvert',0);

INSERT INTO participations (voyage_id,passager_id,statut) VALUES
(1,2,'confirme');

INSERT INTO journal_credits (utilisateur_id,delta,motif) VALUES
(2,-15,'participation'),
(1,+13,'gain'),
(4,+2,'commission');
