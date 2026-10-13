# RAPPORT DE PROJET DE FIN D'ÉTUDES — LICENCE PROFESSIONNELLE 157
## AETERNA : PLATEFORME SAAS MULTI-TENANT D'INSERTION ET DE GESTION DES LAURÉATS
### ÉDITION DESIGN & SOUTENANCE ACADÉMIQUE

---

### PAGE DE GARDE DE PRESTIGE

- **Institution :** Royaume du Maroc — Enseignement Supérieur et Recherche Scientifique
- **Filière :** Licence Professionnelle 157 — Développement Web & Mobile (Université Hassan II de Casablanca)
- **Titre du Projet :** **AETERNA**
- **Sous-titre :** Plateforme SaaS Multi-Tenant pour la Gestion du Réseau des Lauréats, l'Insertion Professionnelle et la Gouvernance Académique
- **Agence Web Fictive Éditrice :** Aeterna Digital Solutions SARL d'Associé Unique (AU)
- **Slogan :** *« Bâtir des ponts numériques durables entre le savoir académique et le monde professionnel »*
- **Auteur & Chef de Projet :** Fregis OMANDA (Lead Full-Stack Developer)
- **Commission d'Évaluation :** Tuteurs académiques et jury de soutenance LP 157
- **Session :** Année Universitaire 2025 – 2026

---

### SOMMAIRE DÉTAILLÉ DU RAPPORT

| Section / Chapitre | Éléments Clés Abordés | Repère Pagination |
| :--- | :--- | :--- |
| **INTRODUCTION GÉNÉRALE** | Contexte marocain, Problématique, Présentation d'Aeterna, Annonce du plan | Page 3 |
| **PARTIE 1 : L'AGENCE WEB FICTIVE** | 1.1 Identité (Nom, logo, slogan, mission, valeurs)<br>1.2 Portée et positionnement (Zone, services, équipe) | Page 4 |
| **PARTIE 2 : ÉTUDE ET STRATÉGIE** | 2.1 Cibles (3 Personas et synthèse de l'enquête terrain de 150 répondants)<br>2.2 Modèle économique B2B SaaS<br>2.3 Marché & Benchmark concurrentiel | Page 6 |
| **PARTIE 3 : CONCEPTION ET RÉALISATION** | 3.1 Conception graphique (Charte Impeccable Design, arborescence, maquettes réelles)<br>3.2 Réalisation technique (Stack Laravel 11/React 18/Inertia, architecture multi-tenant, sécurité CNDP) | Page 9 |
| **PARTIE 4 : LANCEMENT ET GESTION** | 4.1 Stratégie de communication Print, Web et Événementielle<br>4.2 Planning Agile Gantt sur 6 mois & matrice RACI<br>4.3 Budget prévisionnel et compte de résultat sur 3 ans | Page 13 |
| **CONCLUSION GÉNÉRALE** | Bilan technique, retombées sociétales et perspectives d'évolution | Page 16 |
| **ANNEXES COMPLÉMENTAIRES** | Questionnaire d'enquête, Dictionnaire de données relationnel, Conformité Loi CNDP 09-08 | Page 17 |

---

## INTRODUCTION GÉNÉRALE

### 1. Contexte et Idée de Départ
Au Maroc, l'enseignement supérieur connaît une transformation numérique majeure marquée par l'expansion des effectifs universitaires et l'impératif national d'insertion professionnelle rapide des diplômés. Pourtant, la gestion des lauréats (alumni) au sein de la majorité des universités marocaines (FSJES, Facultés des Sciences, ENSA, FST, EST) repose encore sur des outils disparates : fichiers Excel obsolètes, formulaires statiques ou groupes informels sur les réseaux sociaux. 

Ce cloisonnement nuit gravement à l'évaluation de la qualité des formations et prive les promotions actuelles d'un mentorat précieux. L'idée de départ du projet **Aeterna** est de combler cette rupture en concevant une plateforme logicielle cloud SaaS souveraine, spécialement étudiée pour les universités et grandes écoles marocaines, capable de fédérer les promotions, d'automatiser le suivi de l'insertion professionnelle et de connecter directement les diplômés au monde économique.

### 2. Présentation du Projet en Quelques Lignes
**Aeterna** est une application web full-stack développée sous **Laravel 11** et **React 18** via le moteur hybride **Inertia.js**, garantissant une expérience Single Page Application (SPA) réactive sans latence. Elle intègre une architecture multi-tenant native permettant d'héberger plusieurs facultés et campus universitaires avec étanchéité absolue des données et conformité stricte aux exigences de la Commission Nationale de contrôle de la protection des Données à caractère Personnel (**CNDP - Loi 09-08**).

### 3. Annonce du Plan
Ce rapport structure notre démarche selon le référentiel de la Licence Professionnelle 157 :
- **Partie 1 :** L'agence web fictive (**Aeterna Digital Solutions**), son identité et son écosystème ;
- **Partie 2 :** L'étude stratégique, les personas cibles, le modèle économique et le benchmark concurrentiel ;
- **Partie 3 :** La conception graphique et la réalisation technique avec captures réelles et schémas d'architecture ;
- **Partie 4 :** La feuille de route de lancement, le planning Gantt et le budget prévisionnel sur 3 ans.

---

## PARTIE 1 : L'AGENCE WEB FICTIVE
*Objectif : présenter l'entreprise fictive qui réalise le projet, son identité, sa vision et son organisation.*

### 1.1 Identité de l'Agence
- **Nom :** Aeterna Digital Solutions SARL d'AU. Inspiré du latin *æternus* (« durable, intemporel »), ce nom incarne la pérennité du lien unissant un lauréat à son université d'origine.
- **Logo :** Monogramme stylisé associant la lettre « A », une arche académique mauresque et le symbole d'infini, associant le bleu nuit (#16375A) et l'or impérial (#D97706).
- **Slogan :** *« Bâtir des ponts numériques durables entre le savoir académique et le monde professionnel »*.
- **Mission :** Éditer et déployer des solutions logicielles souveraines à forte valeur technologique pour accélérer l'employabilité des jeunes diplômés.
- **Valeurs fondamentales :**
  1. *Excellence technique :* Respect absolu des standards d'ingénierie logicielle (PSR-12, ESLint, TDD, Design System Impeccable).
  2. *Souveraineté des données :* Hébergement sécurisé et conformité totale avec la loi CNDP 09-08 et le RGPD.
  3. *Inclusion & Solidarité :* Rapprocher les générations d'étudiants pour favoriser l'égalité des chances dans l'accès à l'emploi.

### 1.2 Portée et Positionnement
- **Zone de Présence :** Siège à Casablanca (Technopark / Marina), avec un déploiement graduel :
  - *Année 1 :* Universités et grandes écoles du Grand Casablanca-Settat.
  - *Année 2 :* Ensemble des 12 universités publiques marocaines et écoles d'ingénieurs privées.
  - *Année 3 :* Expansion vers l'Afrique subsaharienne francophone (Sénégal, Côte d'Ivoire, Gabon).
- **Offre de Services :** Édition SaaS managée, intégration sur mesure (APIs ENT / Apogée) et conseil en gouvernance de données lauréats.
- **Organisation de l'Équipe :** Fregis Omanda (Chef de projet & Lead Full-Stack Developer), épaulé par des consultants experts en design UI/UX, cloud DevOps et conformité juridique CNDP.

---

## PARTIE 2 : LE PROJET — ÉTUDE ET STRATÉGIE
*Objectif : définir le projet réalisé par l'agence, valider les personas cibles et démontrer sa viabilité économique.*

### 2.1 Cibles du Projet
#### Portraits des Utilisateurs Idéaux (Personas) :
1. **Persona Administrateur — Pr. Amina (52 ans) :** Directrice des Relations Extérieures à l'université. Besoin : disposer d'indicateurs certifiés sur le devenir des promotions pour les dossiers d'accréditation ministérielle. Frustration : taux de réponse de seulement 8% par e-mail conventionnel.
2. **Persona Diplômé — Youssef (27 ans) :** Ingénieur logiciel chez Devoteam Casablanca. Besoin : recruter des stagiaires de confiance de son ancienne filière et valoriser son parcours. Frustration : perte totale du contact avec son école après la remise des diplômes.
3. **Persona Étudiante — Salma (22 ans) :** Étudiante en LP 157 en quête de stage PFE. Besoin : consulter des opportunités exclusives vérifiées et obtenir un mentorat bienveillant. Frustration : candidatures froides sur LinkedIn sans réponse.

#### Synthèse de l'Enquête Terrain (Échantillon de 150 personnes) :
- **92%** des étudiants affirment que le réseau des anciens est déterminant pour l'accès à l'emploi.
- **87%** des diplômés en poste se déclarent prêts à parrainer des étudiants si la plateforme est intuitive et sécurisée.
- **79%** des équipes administratives jugent les méthodes actuelles (fichiers bureautiques) inefficaces et non conformes à la loi 09-08.

### 2.2 Modèle Économique du Projet
Aeterna opère selon un modèle **B2B SaaS Hybride** garantissant des revenus prévisibles :
- **Abonnements Annuels Établissements :**
  - Formule *Campus Essentiel* (jusqu'à 2 500 alumni) : **18 000 MAD HT / an**.
  - Formule *Université Intégrale* (multi-campus, accès illimité, synchronisation ENT) : **42 000 MAD HT / an**.
- **Monétisation Recrutement Entreprises :**
  - *Pack Chasseur de Talents* : **4 500 MAD** pour 5 offres ciblées et accès au vivier certifié.
- **Prestations d'Intégration & Ateliers de Formation :** **8 000 MAD** par établissement.

### 2.3 Marché et Benchmark Concurrentiel
| Critères d'Évaluation | LinkedIn | Hivebrite (Solution UE) | Aeterna SaaS (Notre Solution) |
| :--- | :--- | :--- | :--- |
| **Souveraineté & CNDP** | Non (Serveurs internationaux) | Partielle (RGPD UE) | **Totale (Conformité Loi 09-08)** |
| **Coût Annuel** | Gratuit / Recruiter très cher | Élevé (> 120 000 MAD / an) | **Accessible (18 000 à 42 000 MAD)** |
| **Statistiques Débouchés** | Floues / Non exploitables | Complexes / En anglais | **Tableaux de bord KPI clé en main** |
| **Support & Langues** | Automatisé / Anglais | Support distant | **Support local bilingue (Français/Arabe)** |
| **Architecture Multi-Tenant** | Réseau public généraliste | Complexe à configurer | **Native et isolée par établissement** |

---

## PARTIE 3 : LE PROJET — CONCEPTION ET RÉALISATION
*Objectif : démontrer la rigueur de la conception graphique UX/UI et la qualité de la mise en œuvre technique full-stack.*

### 3.1 Conception Graphique
- **Démarche Impeccable Design :** Contraste WCAG AA validé, typographies équilibrées (*Inter* pour les données tabulaires, *Outfit* pour les titres majeurs).
- **Palette Chromatique :** Bleu Nuit (#16375A), Or Impérial (#D97706), Ardoise doux (#F8FAFC) et fond sombre pour l'immersion (#0F172A).
- **Arrière-Plan Patrimonial Marocain :** Page de connexion valorisant la Mosquée Hassan II et les campus universitaires marocains.
- **Captures d'Écran Réelles Incluses dans le Rapport :**
  - *Illustration 1 :* Écran d'authentification multi-tenant avec décor patrimonial.
  - *Illustration 2 :* Tableau de bord analytique avec indicateurs de placement et graphiques dynamiques.
  - *Illustration 3 :* Annuaire des lauréats avec recherche multicritères et profils enrichis.
  - *Illustration 4 :* Panneau d'administration et gestion des utilisateurs avec avatars certifiés et initiales dynamiques.

### 3.2 Réalisation Technique
- **Stack Technologique :**
  - **Backend :** PHP 8.2+ & Laravel 11 (Eloquent ORM, Migrations, Policies d'accès, Queues asynchrones).
  - **Frontend :** React 18 & Inertia.js (Single Page Application sans latence d'API, Tailwind CSS v3).
  - **Persistance :** MySQL relationnel avec partitionnement logique par établissement (`university_id`).
- **Architecture Multi-Tenant & Sécurité :**
  - Middleware de résolution automatique de l'établissement actif via le sous-domaine ou session.
  - Global Scope Eloquent empêchant toute fuite de données inter-campus.
  - Hachage cryptographique Bcrypt, protection anti-CSRF, requêtes préparées PDO contre les injections SQL.
  - Procédures de suppression conformes au droit à l'oubli de la CNDP.

---

## PARTIE 4 : LANCEMENT ET GESTION DU PROJET
*Objectif : expliquer la stratégie marketing de déploiement, le calendrier prévisionnel et l'ingénierie financière.*

### 4.1 Stratégie de Communication
- **Print Prestige :** Plaquette commerciale 4 pages avec vernis sélectif et chemise officielle cartonnée pour les directions d'universités.
- **Web & Digital :** Campagnes sponsorisées LinkedIn Ads ciblées sur les directeurs de filières et responsables RH.
- **Événementiel :** Webinaire national de lancement « Le suivi des lauréats selon la loi 09-08 » et démonstration sandbox interactive.

### 4.2 Planning et Équipe
- **Méthodologie Scrum :** Sprints de 2 semaines, réunions de cadrage et revues régulières.
- **Calendrier Gantt (6 mois) :**
  - *Mois 1 :* Cadrage fonctionnel et enquête terrain.
  - *Mois 2 :* Prototypage UX/UI et validation de la charte.
  - *Mois 3-4 :* Développement du moteur Laravel, de l'isolation multi-tenant et du frontend React/Inertia.
  - *Mois 5 :* Recette technique, audits de sécurité et tests d'intégration.
  - *Mois 6 :* Soutenance de fin d'études et déploiement des 3 premiers campus pilotes.
- **Matrice RACI :** Fregis Omanda (Réalisateur principal), Comité Pédagogique LP 157 (Approbateur).

### 4.3 Budget Prévisionnel et Rentabilité sur 3 Ans
- **Fonds de Démarrage Requis :** **65 000 MAD** (Constitution de la SARL, hébergement cloud VPS sécurisé, outillage, budget marketing initial).
- **Projections Financières Triennales :**

| Indicateur Financier | Année 1 (Amorçage) | Année 2 (Déploiement National) | Année 3 (Maturité & Maghreb) |
| :--- | :--- | :--- | :--- |
| **Nombre d'Établissements Clients** | 4 campus | 12 campus | 28 campus |
| **Vente de Packs Recrutement** | 10 packs | 35 packs | 85 packs |
| **Chiffre d'Affaires Total** | **117 000 MAD** | **328 000 MAD** | **760 000 MAD** |
| **Charges d'Exploitation** | 85 000 MAD | 183 000 MAD | 380 000 MAD |
| **Résultat Net Bénéficiaire** | **+32 000 MAD** | **+145 000 MAD** | **+380 000 MAD** |
| **Marge Nette** | 27,3 % | 44,2 % | 50,0 % |

*Le seuil de rentabilité (Break-even point) est atteint dès le 7ème mois d'exploitation avec seulement 3 clients réguliers.*

---

## CONCLUSION GÉNÉRALE
Le projet **Aeterna**, développé dans le cadre de la Licence Professionnelle 157, apporte la preuve qu'une solution logicielle d'envergure peut conjuguer rigueur d'ingénierie (Laravel 11, React 18, Multi-Tenant), excellence ergonomique (Impeccable Design) et viabilité commerciale avérée. En répondant concrètement au défi de l'insertion des diplômés au Maroc dans le strict respect de la souveraineté numérique (CNDP), Aeterna ouvre des perspectives concrètes d'évolution, notamment par l'intégration d'un moteur d'IA d'appariement d'offres et d'analyse prédictive de carrières.

---

## ANNEXES COMPLÉMENTAIRES
- **Annexe A :** Questionnaire d'enquête d'insertion professionnelle (5 questions clés).
- **Annexe B :** Dictionnaire de données relationnel (Universités, Utilisateurs, Profils Alumni, Offres d'emploi, Parrainages).
- **Annexe C :** Protocole de conformité CNDP (Loi marocaine 09-08) et gestion du consentement.
