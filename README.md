# Documentation du Bot de Trading Binance pour Synology

## Table des matières

1. [Introduction](#introduction)
2. [Prérequis](#prérequis)
3. [Installation](#installation)
    - [Installation manuelle](#installation-manuelle)
    - [Installation via le script](#installation-via-le-script)
    - [Installation de l'interface web](#installation-de-linterface-web)
4. [Configuration](#configuration)
    - [Configuration des API Binance](#configuration-des-api-binance)
    - [Configuration du bot](#configuration-du-bot)
    - [Configuration des stratégies](#configuration-des-stratégies)
5. [Interface Web](#interface-web)
    - [Accès à l'interface](#accès-à-linterface)
    - [Tableau de bord](#tableau-de-bord)
    - [Gestion des positions](#gestion-des-positions)
    - [Backtesting via l'interface](#backtesting-via-linterface)
    - [Gestion des stratégies](#gestion-des-stratégies-1)
    - [Visualisation des logs](#visualisation-des-logs)
    - [Configuration via l'interface](#configuration-via-linterface)
6. [Stratégies de Trading](#stratégies-de-trading)
    - [Moving Average Crossover](#moving-average-crossover)
    - [RSI (Relative Strength Index)](#rsi-relative-strength-index)
    - [Créer de nouvelles stratégies](#créer-de-nouvelles-stratégies)
7. [Backtesting](#backtesting)
    - [Téléchargement des données historiques](#téléchargement-des-données-historiques)
    - [Exécution d'un backtest](#exécution-dun-backtest)
    - [Analyse des résultats](#analyse-des-résultats)
    - [Optimisation des paramètres](#optimisation-des-paramètres)
8. [Exécution du Bot](#exécution-du-bot)
    - [Mode manuel](#mode-manuel)
    - [Mode daemon](#mode-daemon)
    - [Tâches planifiées](#tâches-planifiées)
9. [Surveillance et Maintenance](#surveillance-et-maintenance)
    - [Logs](#logs)
    - [Gestion des positions](#gestion-des-positions)
    - [Dépannage courant](#dépannage-courant)
10. [Bonnes pratiques et conseils](#bonnes-pratiques-et-conseils)
11. [FAQ](#faq)
12. [Références](#références)

## Introduction

Ce bot de trading est un système automatisé conçu pour effectuer des opérations de trading sur la plateforme Binance en utilisant différentes stratégies techniques. Il est spécialement conçu pour fonctionner sur un NAS Synology, ce qui vous permet de l'exécuter 24h/24 sans avoir besoin de garder votre ordinateur allumé.

Le système comprend deux composants principaux :
- **Un moteur de backtesting** pour tester et optimiser vos stratégies sur des données historiques
- **Un bot de trading** qui exécute ces stratégies en temps réel sur votre compte Binance

Le bot est entièrement écrit en PHP, ce qui le rend facile à déployer sur les NAS Synology, qui incluent généralement PHP dans leur environnement Web Station.

## Prérequis

Avant d'installer le bot, assurez-vous de disposer des éléments suivants :

1. **Un NAS Synology** avec DSM 6.0 ou ultérieur
2. **PHP 7.4 ou supérieur** installé via le Centre de Paquets (package Web Station)
3. **Les extensions PHP suivantes** : curl, json, mbstring, fileinfo
4. **Un compte Binance** avec des API keys générées
5. **Connaissances de base** en trading et en programmation

## Installation

Vous pouvez installer le bot manuellement ou utiliser le script d'installation fourni.

### Installation manuelle

1. Connectez-vous à votre NAS Synology via SSH ou File Station
2. Créez un dossier pour le bot (ex: `/volume1/binance-trading-bot`)
3. Téléchargez ou clonez les fichiers du projet dans ce dossier
4. Assurez-vous que les dossiers `logs` et `data` ont les permissions d'écriture (chmod 777)
5. Configurez vos clés API dans le fichier `config/config.php`

### Installation de l'interface web

L'interface web vous permet de gérer votre bot de trading à travers une interface utilisateur intuitive et responsive, accessible depuis n'importe quel navigateur.

Pour installer l'interface web :

1. Assurez-vous que le bot principal est déjà installé
2. Exécutez le script d'installation de l'interface web :

```bash
cd /chemin/vers/votre/bot
php install_web_interface.php
```

Ce script va :
- Créer la structure de dossiers nécessaire
- Télécharger les bibliothèques requises (Bootstrap, jQuery, Chart.js)
- Configurer les fichiers de sécurité
- Initialiser les fichiers de données nécessaires

3. Configurez un hôte virtuel dans Web Station de Synology :
    - Ouvrez DSM → Panneau de configuration → Web Station
    - Cliquez sur "Portail Web" → "Créer"
    - Nom du portail : "Trading Bot" (ou votre choix)
    - Document root : /chemin/vers/votre/bot/web
    - Port : 80 (ou votre choix)
    - PHP : Activé

4. Vous pouvez maintenant accéder à l'interface en visitant http://votre-nas-ip:port/ dans votre navigateur

## Interface Web

L'interface web vous permet de gérer et surveiller votre bot de trading depuis n'importe quel navigateur, sur n'importe quel appareil (PC, tablette, smartphone). Elle est conçue pour être intuitive, responsive et sécurisée.

### Accès à l'interface

Après avoir installé l'interface web, vous pouvez y accéder en visitant :
```
http://[adresse-ip-de-votre-nas]:[port]/
```

Par défaut, les identifiants sont :
- Nom d'utilisateur : `admin`
- Mot de passe : `admin`

**Important** : Changez immédiatement ces identifiants dans la page Paramètres après votre première connexion.

### Tableau de bord

Le tableau de bord fournit une vue d'ensemble de votre activité de trading :

- **Statistiques clés** : Solde actuel, équité totale, nombre de positions ouvertes, taux de réussite
- **Graphique d'équité** : Évolution de votre équité totale au fil du temps
- **Positions ouvertes** : Liste des positions actuellement ouvertes avec détails et possibilité de vendre
- **Derniers trades** : Historique des dernières transactions effectuées par le bot

Les informations sont mises à jour automatiquement selon l'intervalle configuré (par défaut toutes les minutes).

### Gestion des positions

La page Positions vous permet de :

- **Voir toutes vos positions ouvertes** en détail (prix d'entrée, prix actuel, profit/perte, etc.)
- **Vendre manuellement** une position si nécessaire
- **Consulter l'historique complet** de vos trades avec filtrage par symbole ou résultat
- **Analyser vos performances** : taux de réussite, profit net, facteur de profit, etc.

### Backtesting via l'interface

L'interface de backtesting vous permet de tester vos stratégies sans écrire de code :

- **Sélection simple** de la stratégie et des paramètres
- **Configuration des périodes** de test et des montants
- **Visualisation graphique** des résultats
- **Analyse détaillée** des performances
- **Tableau des trades** effectués pendant le backtest
- **Possibilité d'utiliser directement** une stratégie performante pour le trading réel

### Gestion des stratégies

La page Stratégies vous permet de :

- **Voir les stratégies disponibles** avec leurs descriptions et paramètres
- **Configurer les paramètres** des stratégies selon vos préférences
- **Activer une stratégie** pour le trading en direct
- **Utiliser des variantes prédéfinies** (conservatrice, agressive, etc.)
- **Démarrer ou arrêter le bot** directement depuis l'interface

### Visualisation des logs

La page Logs vous offre une vue complète sur l'activité du bot :

- **Filtrage par niveau** (info, warning, error, debug)
- **Recherche textuelle** dans les logs
- **Code couleur** pour identifier rapidement les informations importantes
- **Actualisation automatique** pour suivre l'activité en temps réel
- **Possibilité d'effacer** les logs anciens

### Configuration via l'interface

La page Paramètres vous permet de configurer tous les aspects du bot :

- **API Binance** : Clés API, mode test
- **Paramètres de trading** : Devise de base, montants, stop-loss, take-profit, etc.
- **Planification** : Intervalle entre les vérifications du marché
- **Backtest** : Périodes par défaut, balance initiale
- **Notifications** : Email, Telegram
- **Journalisation** : Niveau de détail des logs
- **Interface web** : Sécurité, actualisation, affichage

### Configuration du bot

Le fichier principal de configuration se trouve dans `config/config.php`. Voici les principaux paramètres à ajuster :

#### Configuration du trading

```php
'trading' => [
    'base_currency' => 'USDT',  // Devise de base pour le trading
    'symbols' => ['BTC', 'ETH', 'BNB'],  // Paires à trader
    'investment_per_trade' => 100,  // Montant à investir par trade (en USDT)
    'stop_loss_percentage' => 2.5,  // Pourcentage de stop loss
    'take_profit_percentage' => 5,  // Pourcentage de prise de profit
    'max_open_positions' => 3,  // Nombre maximum de positions ouvertes
],
```

- `base_currency` : La devise utilisée pour acheter d'autres cryptomonnaies (généralement USDT)
- `symbols` : Liste des cryptomonnaies à trader (le bot créera les paires avec la devise de base, ex: BTCUSDT)
- `investment_per_trade` : Montant à investir à chaque trade
- `stop_loss_percentage` : Pourcentage de perte à partir duquel le bot vendra automatiquement
- `take_profit_percentage` : Pourcentage de gain à partir duquel le bot vendra automatiquement
- `max_open_positions` : Nombre maximum de positions que le bot peut avoir en même temps

#### Configuration de la planification

```php
'schedule' => [
    'check_interval' => 300,  // Intervalle de vérification en secondes (5 minutes)
],
```

- `check_interval` : Temps d'attente entre chaque vérification du marché (en secondes)

#### Configuration du backtest

```php
'backtest' => [
    'start_date' => '2023-01-01',  // Date de début du backtest
    'end_date' => '2023-12-31',  // Date de fin du backtest
    'initial_balance' => 1000,  // Balance initiale pour le backtest (en USDT)
],
```

- `start_date` : Date de début pour les données historiques
- `end_date` : Date de fin pour les données historiques
- `initial_balance` : Solde initial virtuel pour le backtest

#### Configuration des notifications

```php
'notifications' => [
    'email' => [
        'enabled' => false,
        'address' => 'votre-email@exemple.com',
    ],
    'telegram' => [
        'enabled' => false,
        'bot_token' => '',
        'chat_id' => '',
    ],
],
```

- Permet de configurer les notifications par email ou Telegram (requiert une configuration supplémentaire)

#### Configuration des logs

```php
'logging' => [
    'level' => 'info',  // debug, info, warning, error
    'file' => __DIR__ . '/../logs/trading.log',
],
```

- `level` : Niveau de détail des logs (debug pour plus de détails, error pour les erreurs uniquement)
- `file` : Emplacement du fichier de log

### Configuration des stratégies

Les paramètres des stratégies sont définis dans `config/strategies.php`. Chaque stratégie a plusieurs variantes (default, aggressive, conservative) avec des paramètres différents.

```php
'moving_average' => [
    'default' => [
        'short_period' => 9,
        'long_period' => 21,
        'price_index' => 4,
    ],
    'aggressive' => [
        'short_period' => 5,
        'long_period' => 15,
        'price_index' => 4,
    ],
    // ...
],
```

Pour utiliser une variante spécifique d'une stratégie, vous pouvez la spécifier lors du lancement du bot :

```bash
php run.php --strategy=MovingAverageStrategy --params "short_period=7 long_period=21"
```

## Stratégies de Trading

Le bot inclut deux stratégies principales, mais vous pouvez en créer d'autres en implémentant l'interface `StrategyInterface`.

### Moving Average Crossover

Cette stratégie est basée sur le croisement de deux moyennes mobiles simples (SMA) :

- **Signal d'achat** : Quand la moyenne mobile courte croise au-dessus de la moyenne mobile longue (Golden Cross)
- **Signal de vente** : Quand la moyenne mobile courte croise en-dessous de la moyenne mobile longue (Death Cross)

Paramètres ajustables :
- `short_period` : Période de la moyenne mobile courte
- `long_period` : Période de la moyenne mobile longue
- `price_index` : Indice du prix dans les données de klines (4 = prix de clôture)

### RSI (Relative Strength Index)

Cette stratégie est basée sur l'indicateur de force relative (RSI) qui mesure la vitesse et le changement des mouvements de prix :

- **Signal d'achat** : Quand le RSI sort de la zone de survente (remonte au-dessus du niveau `oversold`)
- **Signal de vente** : Quand le RSI sort de la zone de surachat (redescend en-dessous du niveau `overbought`)

Paramètres ajustables :
- `period` : Période pour le calcul du RSI
- `overbought` : Niveau de surachat (généralement 70)
- `oversold` : Niveau de survente (généralement 30)
- `price_index` : Indice du prix dans les données de klines (4 = prix de clôture)

### Créer de nouvelles stratégies

Pour créer une nouvelle stratégie, vous devez :

1. Créer une classe qui implémente `StrategyInterface`
2. Implémenter les méthodes requises (`shouldBuy`, `shouldSell`, etc.)
3. Placer le fichier dans le dossier `src/Strategy/`
4. Ajouter la configuration dans `config/strategies.php`

Exemple minimal de nouvelle stratégie :

```php
<?php

require_once __DIR__ . '/StrategyInterface.php';

class MaNouvelleLaStrategie implements StrategyInterface {
    private $params = [
        'parameter1' => 10,
        'parameter2' => 20,
    ];
    
    public function shouldBuy(array $marketData): bool {
        // Votre logique d'achat ici
        return false;
    }
    
    public function shouldSell(array $marketData, array $position): bool {
        // Votre logique de vente ici
        return false;
    }
    
    public function getName(): string {
        return 'Ma Nouvelle Stratégie';
    }
    
    public function getDescription(): string {
        return 'Description de ma nouvelle stratégie.';
    }
    
    public function setParameters(array $params): void {
        foreach ($params as $key => $value) {
            if (array_key_exists($key, $this->params)) {
                $this->params[$key] = $value;
            }
        }
    }
    
    public function getParameters(): array {
        return $this->params;
    }
}
```

## Backtesting

Le backtesting vous permet de tester vos stratégies sur des données historiques avant de les utiliser en trading réel.

### Téléchargement des données historiques

Pour télécharger les données historiques, exécutez :

```bash
php backtest.php --download
```

Cela téléchargera les données historiques pour la période spécifiée dans `config/config.php` et les sauvegardera dans le dossier `data/historical/`.

### Exécution d'un backtest

Pour exécuter un backtest sur les stratégies disponibles :

```bash
php backtest.php
```

Cela testera toutes les stratégies disponibles et générera des fichiers de résultats dans le dossier `data/`.

Pour tester une stratégie spécifique avec des paramètres personnalisés :

```bash
php backtest.php MovingAverageStrategy --params short_period=5 long_period=15
```

### Analyse des résultats

Les résultats du backtest incluent les métriques suivantes :

- **Profit total** : Gain ou perte en valeur absolue et en pourcentage
- **Nombre de trades** : Total, gagnants et perdants
- **Taux de réussite** : Pourcentage de trades gagnants
- **Facteur de profit** : Ratio entre profits et pertes
- **Drawdown maximum** : Plus grande baisse de la courbe d'équité
- **Frais payés** : Total des frais de transaction

Ces résultats sont sauvegardés dans des fichiers JSON pour une analyse ultérieure.

### Optimisation des paramètres

Pour trouver les meilleurs paramètres pour une stratégie, vous pouvez exécuter plusieurs backtests avec différentes combinaisons de paramètres.

Exemple de script d'optimisation (à créer) :

```php
<?php

// Inclure les fichiers nécessaires
require_once __DIR__ . '/src/BinanceAPI.php';
// ...

// Stratégie à optimiser
$strategy = new MovingAverageStrategy();

// Plages de paramètres à tester
$shortPeriods = range(5, 20, 5);  // 5, 10, 15, 20
$longPeriods = range(20, 50, 10);  // 20, 30, 40, 50

$bestResult = null;
$bestProfit = -INF;

// Charger les données historiques
$dataLoader = new DataLoader($binanceAPI);
$historicalData = $dataLoader->loadFromCSV(__DIR__ . '/data/historical/BTCUSDT_1h.csv');

// Tester toutes les combinaisons
foreach ($shortPeriods as $shortPeriod) {
    foreach ($longPeriods as $longPeriod) {
        // Ignorer les combinaisons invalides
        if ($shortPeriod >= $longPeriod) {
            continue;
        }
        
        // Configurer la stratégie
        $strategy->setParameters([
            'short_period' => $shortPeriod,
            'long_period' => $longPeriod
        ]);
        
        // Exécuter le backtest
        $backtester = new BacktestEngine($strategy, $historicalData, $config);
        $result = $backtester->run();
        
        // Vérifier si c'est le meilleur résultat
        if ($result['profit'] > $bestProfit) {
            $bestProfit = $result['profit'];
            $bestResult = $result;
        }
        
        echo "Testé : short_period={$shortPeriod}, long_period={$longPeriod}, profit={$result['profit']}\n";
    }
}

// Afficher le meilleur résultat
echo "\nMeilleure combinaison :\n";
echo "short_period=" . $bestResult['parameters']['short_period'] . "\n";
echo "long_period=" . $bestResult['parameters']['long_period'] . "\n";
echo "Profit : " . $bestResult['profit'] . " (" . $bestResult['profit_pct'] . "%)\n";
echo "Win rate : " . $bestResult['win_rate'] . "%\n";
```

## Exécution du Bot

### Mode manuel

Pour exécuter le bot une seule fois (pour vérifier les signaux actuels sans trader automatiquement) :

```bash
php run.php
```

Par défaut, le bot utilisera la stratégie MovingAverageStrategy. Pour spécifier une autre stratégie :

```bash
php run.php --strategy=RSIStrategy
```

Pour utiliser des paramètres personnalisés :

```bash
php run.php --strategy=RSIStrategy --params "period=14 oversold=25 overbought=75"
```

### Mode daemon

Pour exécuter le bot en continu (mode daemon) :

```bash
php run.php --daemon
```

En mode daemon, le bot vérifiera le marché à l'intervalle spécifié dans la configuration (`check_interval`).

Si vous avez utilisé le script d'installation, vous pouvez également utiliser les scripts créés :

```bash
# Pour démarrer le daemon
./bin/daemon.sh

# Pour arrêter le daemon
./bin/stop.sh
```

### Tâches planifiées

Vous pouvez configurer une tâche planifiée dans DSM pour exécuter le bot à intervalles réguliers :

1. Ouvrez DSM → Panneau de configuration → Planificateur de tâches
2. Cliquez sur Créer → Tâche planifiée → Script défini par l'utilisateur
3. Configurez la planification (par exemple, toutes les heures)
4. Dans le champ "Exécuter la commande", entrez : `php /chemin/vers/votre/bot/run.php`
5. Cliquez sur OK pour enregistrer

## Surveillance et Maintenance

### Logs

Les logs du bot sont stockés dans le dossier `logs/`. Vous pouvez les consulter pour suivre l'activité du bot et diagnostiquer les problèmes.

Exemple de log :

```
2023-03-01 12:00:00 [info] Démarrage du bot de trading avec la stratégie: Moving Average Crossover
2023-03-01 12:00:01 [info] Aucune position existante trouvée
2023-03-01 12:00:05 [info] Signal d'achat détecté pour BTCUSDT
2023-03-01 12:00:06 [info] Achat réussi de 0.00452 BTCUSDT au prix de 22100.50
```

Vous pouvez ajuster le niveau de détail des logs dans `config/config.php` :
- `debug` : Informations très détaillées (utile pour le développement)
- `info` : Informations générales sur le fonctionnement
- `warning` : Avertissements non critiques
- `error` : Erreurs qui empêchent le fonctionnement normal

### Gestion des positions

Le bot enregistre les positions ouvertes dans `data/positions.json`. Vous pouvez consulter ce fichier pour voir les positions actuelles.

**Note** : Ne modifiez pas ce fichier manuellement pendant que le bot est en cours d'exécution.

Exemple de contenu :

```json
{
    "BTCUSDT": {
        "symbol": "BTCUSDT",
        "entry_price": 22100.50,
        "quantity": 0.00452,
        "timestamp": 1677672006000,
        "cost": 100,
        "current_price": 22150.75,
        "current_value": 100.12,
        "profit_loss": 0.12,
        "profit_loss_pct": 0.12,
        "order_id": 123456789
    }
}
```

### Dépannage courant

#### Le bot ne se connecte pas à Binance

- Vérifiez que vos clés API sont correctes
- Vérifiez que les restrictions IP sont configurées correctement
- Assurez-vous que les permissions de trading sont activées

#### Le bot ne détecte pas les signaux

- Vérifiez que les paramètres de la stratégie sont corrects
- Assurez-vous que les données de marché sont récupérées correctement
- Consultez les logs pour plus de détails

#### Le bot génère des erreurs

Les erreurs courantes incluent :
- "PHP Extension X is missing" : Installez l'extension PHP manquante
- "API error: Invalid API-key" : Vérifiez vos clés API
- "Insufficient balance" : Fonds insuffisants sur votre compte Binance

## Bonnes pratiques et conseils

1. **Commencez petit** : Utilisez de petits montants pour vos premiers trades réels
2. **Testez abondamment** : Effectuez des backtests approfondis avant de passer au trading réel
3. **Surveillez régulièrement** : Vérifiez les logs et les performances de votre bot
4. **Diversifiez les stratégies** : Utilisez différentes stratégies pour différentes conditions de marché
5. **Attention aux frais** : Les frais peuvent significativement impacter la rentabilité sur le long terme
6. **Ajustez les paramètres** : Optimisez régulièrement les paramètres en fonction des conditions de marché
7. **Sauvegardez** : Effectuez des sauvegardes régulières de votre configuration et de vos données
8. **Sécurisez l'interface web** : Changez les identifiants par défaut et limitez l'accès si possible
9. **Utilisez le mode test** : Commencez toujours par utiliser le mode test avant de passer au trading réel
10. **Consultez les statistiques** : Analysez les performances via le tableau de bord pour améliorer vos stratégies

### Sécurisation de l'interface web

Pour renforcer la sécurité de votre interface web :

1. **Changez les identifiants par défaut** dès la première connexion
2. **Utilisez un mot de passe fort** (12+ caractères, incluant majuscules, minuscules, chiffres et symboles)
3. **Configurez HTTPS** si possible via le panneau DSM de Synology
4. **Limitez l'accès par IP** si vous accédez toujours depuis les mêmes réseaux
5. **Ne partagez pas l'URL** de votre interface publiquement
6. **Vérifiez régulièrement les logs** pour détecter des tentatives d'accès non autorisées

### Maintenance régulière

Pour maintenir votre bot en bon état de fonctionnement :

1. **Vérifiez les logs** quotidiennement pour détecter d'éventuels problèmes
2. **Surveillez l'espace disque** disponible sur votre NAS
3. **Mettez à jour les paramètres** en fonction de l'évolution du marché
4. **Sauvegardez la configuration** avant de faire des modifications importantes
5. **Testez régulièrement** de nouvelles stratégies en mode backtest
6. **Redémarrez le bot** périodiquement pour éviter les problèmes de mémoire

## FAQ

**Q: Le bot peut-il fonctionner sur d'autres plateformes que Synology ?**
R: Oui, il peut fonctionner sur n'importe quel système avec PHP 7.4 ou supérieur et les extensions requises.

**Q: Puis-je utiliser d'autres exchanges que Binance ?**
R: Non, le bot est spécifiquement conçu pour Binance. Pour d'autres exchanges, il faudrait modifier la classe `BinanceAPI`.

**Q: Le bot est-il sécurisé ?**
R: Le bot utilise uniquement les permissions de lecture et de trading, pas les permissions de retrait. Cependant, il est recommandé de ne pas utiliser votre compte principal et de limiter les fonds disponibles.

**Q: Combien de paires puis-je trader simultanément ?**
R: Techniquement, autant que vous le souhaitez, mais cela dépend de votre capital et des limites de l'API Binance. Il est recommandé de commencer avec 1-3 paires.

**Q: Le bot fonctionne-t-il 24h/24 ?**
R: Oui, en mode daemon ou via des tâches planifiées, le bot peut fonctionner en continu.

**Q: Que se passe-t-il en cas de coupure de courant ou de redémarrage du NAS ?**
R: Si vous avez configuré une tâche de démarrage, le bot redémarrera automatiquement. Sinon, vous devrez le redémarrer manuellement.

**Q: L'interface web est-elle accessible depuis l'extérieur de mon réseau local ?**
R: Par défaut, elle n'est accessible que sur votre réseau local. Pour un accès externe, vous devez configurer correctement les paramètres de votre routeur et la sécurité de votre NAS.

**Q: Puis-je recevoir des notifications sur mon téléphone ?**
R: Oui, vous pouvez configurer des notifications via Telegram dans les paramètres. Une fois configuré, le bot vous alertera des trades, positions ouvertes/fermées et erreurs éventuelles.

**Q: Est-il possible d'ajouter mes propres indicateurs techniques ?**
R: Oui, vous pouvez créer de nouvelles stratégies en implémentant l'interface `StrategyInterface` et en ajoutant vos propres indicateurs.

**Q: Le bot peut-il trader des contrats à terme (futures) ?**
R: Non, actuellement le bot ne supporte que le trading spot. Le support des futures nécessiterait des modifications importantes.

**Q: Comment puis-je suivre mes performances sur mobile ?**
R: L'interface web est entièrement responsive et s'adapte aux écrans mobiles. Vous pouvez donc consulter votre tableau de bord et gérer vos positions depuis votre smartphone ou tablette.

## Références

- [Documentation API Binance](https://binance-docs.github.io/apidocs/)
- [PHP Documentation](https://www.php.net/docs.php)
- [Trading avec moyennes mobiles](https://www.investopedia.com/terms/m/movingaverage.asp)
- [Trading avec RSI](https://www.investopedia.com/terms/r/rsi.asp)
- [Bootstrap Documentation](https://getbootstrap.com/docs/) (pour l'interface web)
- [Chart.js Documentation](https://www.chartjs.org/docs/) (pour les graphiques de l'interface)
- [Documentation Synology Web Station](https://www.synology.com/en-global/knowledgebase/DSM/help/WebStation/webstation_desc)
- [Guide d'utilisation de l'API Telegram](https://core.telegram.org/bots/api) (pour les notifications)

### Remerciements

Un grand merci à tous les contributeurs et aux utilisateurs qui ont partagé leurs retours pour améliorer ce bot. Vos suggestions et rapports de bugs ont été essentiels pour faire évoluer cet outil.

Si vous avez des suggestions d'amélioration ou si vous rencontrez des problèmes, n'hésitez pas à créer une issue sur le dépôt GitHub du projet ou à contacter l'auteur.

### Avertissement

Le trading de cryptomonnaies comporte des risques significatifs. Ce bot est fourni à titre éducatif et expérimental. L'auteur décline toute responsabilité pour les pertes financières qui pourraient résulter de son utilisation. Assurez-vous de comprendre les risques associés au trading algorithmique avant d'utiliser ce bot avec des fonds réels.

Utilisez toujours le mode test en premier, commencez avec de petits montants, et ne tradez jamais plus que ce que vous pouvez vous permettre de perdre.
