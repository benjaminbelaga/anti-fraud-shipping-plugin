=== WooCommerce Fraud Shield ===
Contributors: yoyaku-team
Tags: woocommerce, security, fraud, protection, honeypot
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Système de protection anti-fraude sophistiqué pour WooCommerce avec détection honeypot et frais de sécurité automatiques.

== Description ==

WooCommerce Fraud Shield est un plugin professionnel de protection anti-fraude spécialement conçu pour yoyaku.io. Il offre une protection complète contre les tentatives de fraude avec un système de détection honeypot sophistiqué.

= Fonctionnalités Principales =

* **🕷️ Détection Honeypot** : Surveillance automatique du produit piège (ID: 604098)
* **💰 Frais de Sécurité** : Application automatique de 9999€ en cas de détection
* **🚨 Système d'Alertes** : Notifications instantanées par email
* **📊 Interface Admin** : Dashboard complet avec statistiques en temps réel
* **🔍 Analyse de Risque** : Score sophistiqué basé sur de multiples facteurs
* **⚡ Compatibilité HPOS** : Support officiel du High-Performance Order Storage
* **📋 Logs Complets** : Traçabilité complète de toutes les activités

= Compatibilité =

* WordPress 5.8+
* WooCommerce 8.0+
* PHP 7.4+
* Compatible HPOS (High-Performance Order Storage)
* Compatible avec tous les thèmes WooCommerce

= Sécurité =

* Protection contre l'accès direct aux fichiers
* Validation et sanitisation de toutes les données
* Nonces WordPress pour toutes les actions AJAX
* Respect des standards de sécurité WordPress

== Installation ==

1. Téléchargez le plugin
2. Activez WooCommerce (prérequis obligatoire)
3. Installez et activez WooCommerce Fraud Shield
4. Allez dans WooCommerce > Fraud Shield pour configurer
5. Activez la protection via le master switch

== Configuration ==

1. **Protection Active** : Activer/désactiver le système complet
2. **Frais de Sécurité Auto** : Application automatique des frais en cas de détection
3. **Seuil d'Alerte** : Score de risque minimum pour les alertes (recommandé: 60%)
4. **Alertes Email** : Notifications automatiques à l'administrateur
5. **Logs Complets** : Enregistrement de toutes les activités

== Frequently Asked Questions ==

= Qu'est-ce qu'un produit honeypot ? =

Un produit honeypot (ID: 604098) est un produit piège invisible aux clients légitimes mais détectable par les bots malveillants. Toute tentative d'ajout au panier déclenche automatiquement l'application de frais de sécurité.

= Pourquoi 9999€ de frais de sécurité ? =

Ce montant dissuasif empêche efficacement la finalisation des commandes frauduleuses tout en identifiant clairement les tentatives malveillantes.

= Le plugin est-il compatible HPOS ? =

Oui, WooCommerce Fraud Shield déclare officiellement sa compatibilité avec le High-Performance Order Storage (HPOS) de WooCommerce.

= Comment fonctionne l'analyse de risque ? =

Le système analyse plusieurs facteurs : email, géolocalisation, montant de commande, patterns comportementaux, adresses IP suspectes, et plus encore pour calculer un score de risque global.

= Les logs sont-ils sécurisés ? =

Oui, tous les logs sont stockés dans un dossier protégé avec fichiers .htaccess empêchant l'accès direct depuis le web.

== Screenshots ==

1. Dashboard principal avec statistiques en temps réel
2. Interface de configuration avancée
3. Système de logs et monitoring
4. Informations système et compatibilité
5. Alertes et notifications en temps réel

== Changelog ==

= 1.0.0 =
* Release initiale
* Système de détection honeypot complet
* Application automatique des frais de sécurité
* Interface admin moderne avec statistiques
* Compatibilité HPOS officielle
* Analyse de risque sophistiquée
* Système de logs et alertes complet
* Standards WordPress/WooCommerce professionnels

== Upgrade Notice ==

= 1.0.0 =
Version initiale du plugin. Installation recommandée pour tous les sites yoyaku.io nécessitant une protection anti-fraude professionnelle.

== Technical Details ==

= System Requirements =
* WordPress 5.8 ou supérieur
* WooCommerce 8.0 ou supérieur
* PHP 7.4 ou supérieur
* MySQL 5.6 ou supérieur

= Performance =
* Impact minimal sur les performances (< 0.01s)
* Optimisé pour les gros volumes de commandes
* Compatible avec tous les systèmes de cache

= Developer Notes =
* Code entièrement orienté objet (POO)
* Respect des standards WordPress Coding Standards
* Hooks et filtres disponibles pour extensions
* Documentation complète du code

= Security Features =
* Validation stricte de toutes les entrées
* Sanitisation automatique des données
* Protection CSRF avec nonces WordPress
* Logs sécurisés avec protection .htaccess