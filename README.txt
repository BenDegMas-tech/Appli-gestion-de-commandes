GESTION DES COMMANDES - GUIDE D'INSTALLATION
=====================================

Table des matières
-----------------
1. Prérequis
2. Installation
3. Configuration
4. Structure des dossiers
5. Sécurité
6. Dépannage

1. PRÉREQUIS
------------
- PHP 8.2 ou supérieur
- MySQL/MariaDB 5.7 ou supérieur
- Serveur Web (Apache recommandé)
- Composer (gestionnaire de dépendances PHP)
- Extensions PHP requises :
  * PDO et PDO_MySQL
  * mbstring
  * json
  * curl
  * gd (pour la génération des QR codes)

2. INSTALLATION
--------------
a) Cloner ou décompresser l'application dans votre répertoire web

b) Configuration de la base de données :
   - Créer une base de données MySQL
   - Importer le fichier database_schema.sql :
     mysql -u votre_utilisateur -p nom_de_la_base < database_schema.sql

c) Installation des dépendances :
   - Ouvrir un terminal dans le dossier de l'application
   - Exécuter : php composer.phar install
   - Ou si Composer est installé globalement : composer install

d) Configuration des permissions :
   - Donner les droits d'écriture aux dossiers :
     * /logs
     * /uploads
     * /reports
   - Commandes (Linux/Mac) :
     chmod -R 755 .
     chmod -R 777 logs uploads reports

3. CONFIGURATION
---------------
a) Copier et renommer le fichier de configuration :
   - Copier api/config.php.example vers api/config.php
   - Modifier les paramètres dans config.php :
     * DB_HOST : hôte de la base de données
     * DB_NAME : nom de la base de données
     * DB_USER : utilisateur de la base de données
     * DB_PASS : mot de passe de la base de données
     * APP_URL : URL de base de l'application
     * SENDGRID_API_KEY : clé API SendGrid pour les emails

b) Configuration email (SendGrid) :
   - Créer un compte sur SendGrid
   - Générer une clé API
   - Configurer le domaine d'envoi
   - Mettre à jour les paramètres dans config.php

4. STRUCTURE DES DOSSIERS
------------------------
/api            - Classes et fonctions principales
/assets         - Fichiers statiques (CSS, JS, images)
/includes       - Fichiers inclus et utilitaires
/logs          - Fichiers de logs
/reports        - Rapports générés
/uploads        - Fichiers uploadés
/vendor         - Dépendances Composer

5. SÉCURITÉ
-----------
a) Fichiers à protéger :
   - Ajouter dans .htaccess :
     deny from all
   Pour les dossiers :
   - /logs
   - /reports (sauf si accès public nécessaire)
   - /api

b) Compte administrateur par défaut :
   - Email : admin@example.com
   - Mot de passe : admin123
   IMPORTANT : Changer le mot de passe après la première connexion !

6. DÉPANNAGE
------------
a) Erreurs courantes :
   - "Class not found" : Exécuter composer dump-autoload
   - Erreur de connexion BDD : Vérifier config.php
   - Erreur de permissions : Vérifier les droits des dossiers

b) Logs :
   - Consulter /logs/debug.log pour les erreurs
   - Activer le mode DEBUG dans config.php si nécessaire

c) Support :
   Pour toute assistance, contacter :
   - Email : support@chambre27.com
   - Téléphone : [Votre numéro de support]

Note : Après l'installation, supprimer les fichiers suivants :
- install.php
- composer-installer.php
- install_dependencies.php
- update_dependencies.php
- test_*.php 