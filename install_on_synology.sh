#!/bin/bash

# Script d'installation du bot de trading Binance sur NAS Synology
# --------------------------------------------------------------

# Définir les couleurs pour une meilleure lisibilité
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Installation du Bot de Trading Binance sur Synology ===${NC}"
echo ""

# 1. Vérifier que PHP est installé
echo -e "${YELLOW}Vérification de l'installation de PHP...${NC}"
if ! command -v php &> /dev/null; then
    echo -e "${RED}PHP n'est pas installé sur votre système.${NC}"
    echo "Veuillez installer PHP via le Centre de Paquets de DSM."
    echo "Recherchez 'Web Station' et 'PHP' et installez-les."
    exit 1
fi

PHP_VERSION=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 1,2)
echo -e "${GREEN}PHP $PHP_VERSION est installé.${NC}"

# 2. Vérifier les extensions PHP nécessaires
echo -e "${YELLOW}Vérification des extensions PHP requises...${NC}"
REQUIRED_EXTENSIONS=("curl" "json" "mbstring" "fileinfo")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! php -m | grep -q $ext; then
        MISSING_EXTENSIONS+=($ext)
    fi
done

if [ ${#MISSING_EXTENSIONS[@]} -ne 0 ]; then
    echo -e "${RED}Les extensions PHP suivantes sont manquantes :${NC}"
    for ext in "${MISSING_EXTENSIONS[@]}"; do
        echo " - $ext"
    done
    echo "Veuillez les installer via le Centre de Paquets > Web Station > PHP > Extensions."
    exit 1
fi

echo -e "${GREEN}Toutes les extensions PHP requises sont installées.${NC}"

# 3. Demander le chemin d'installation
echo -e "${YELLOW}Veuillez spécifier le chemin d'installation :${NC}"
echo "Par défaut : /volume1/binance-trading-bot"
read -p "Chemin d'installation [/volume1/binance-trading-bot] : " INSTALL_PATH
INSTALL_PATH=${INSTALL_PATH:-/volume1/binance-trading-bot}

# 4. Créer le dossier d'installation
echo -e "${YELLOW}Création du dossier d'installation...${NC}"
mkdir -p "$INSTALL_PATH"

if [ ! -d "$INSTALL_PATH" ]; then
    echo -e "${RED}Impossible de créer le dossier d'installation.${NC}"
    echo "Veuillez vérifier les droits d'accès et réessayer."
    exit 1
fi

echo -e "${GREEN}Dossier d'installation créé : $INSTALL_PATH${NC}"

# 5. Copier les fichiers du projet
echo -e "${YELLOW}Copie des fichiers du projet...${NC}"

# Si script exécuté depuis un répertoire contenant les fichiers
if [ -f "run.php" ] && [ -f "backtest.php" ]; then
    cp -R * "$INSTALL_PATH"
else
    # Sinon, créer la structure de base
    mkdir -p "$INSTALL_PATH/config"
    mkdir -p "$INSTALL_PATH/src/Strategy"
    mkdir -p "$INSTALL_PATH/src/Backtest"
    mkdir -p "$INSTALL_PATH/src/Utils"
    mkdir -p "$INSTALL_PATH/data/historical"
    mkdir -p "$INSTALL_PATH/logs"

    # À adapter selon votre méthode de distribution des fichiers
    echo -e "${YELLOW}Structure de dossiers créée. Veuillez copier manuellement les fichiers du projet dans $INSTALL_PATH${NC}"
fi

echo -e "${GREEN}Fichiers copiés avec succès.${NC}"

# 6. Configurer les permissions
echo -e "${YELLOW}Configuration des permissions...${NC}"
chmod -R 755 "$INSTALL_PATH"
chmod -R 777 "$INSTALL_PATH/logs"
chmod -R 777 "$INSTALL_PATH/data"
echo -e "${GREEN}Permissions configurées.${NC}"

# 7. Créer une tâche planifiée
echo -e "${YELLOW}Voulez-vous configurer une tâche planifiée pour exécuter le bot ? (o/n)${NC}"
read -p "Configurer une tâche planifiée [o] : " CREATE_TASK
CREATE_TASK=${CREATE_TASK:-o}

if [[ $CREATE_TASK == "o" || $CREATE_TASK == "O" ]]; then
    echo -e "${YELLOW}À quelle fréquence souhaitez-vous exécuter le bot ?${NC}"
    echo "1) Toutes les heures"
    echo "2) Toutes les 4 heures"
    echo "3) Une fois par jour"
    echo "4) En mode daemon (continu)"
    read -p "Choix [1] : " TASK_FREQUENCY
    TASK_FREQUENCY=${TASK_FREQUENCY:-1}

    CRON_EXPRESSION=""
    COMMAND=""

    case $TASK_FREQUENCY in
        1)
            CRON_EXPRESSION="0 * * * *"
            COMMAND="php $INSTALL_PATH/run.php"
            ;;
        2)
            CRON_EXPRESSION="0 */4 * * *"
            COMMAND="php $INSTALL_PATH/run.php"
            ;;
        3)
            CRON_EXPRESSION="0 0 * * *"
            COMMAND="php $INSTALL_PATH/run.php"
            ;;
        4)
            echo -e "${YELLOW}Configuration du mode daemon...${NC}"
            DAEMON_SCRIPT="$INSTALL_PATH/daemon.sh"

            # Créer le script du daemon
            cat > "$DAEMON_SCRIPT" << EOF
#!/bin/bash
cd $INSTALL_PATH
php run.php --daemon > /dev/null 2>&1 &
echo \$! > $INSTALL_PATH/bot.pid
EOF

            chmod +x "$DAEMON_SCRIPT"

            # Créer le script d'arrêt
            STOP_SCRIPT="$INSTALL_PATH/stop.sh"
            cat > "$STOP_SCRIPT" << EOF
#!/bin/bash
if [ -f "$INSTALL_PATH/bot.pid" ]; then
    kill \$(cat $INSTALL_PATH/bot.pid)
    rm $INSTALL_PATH/bot.pid
    echo "Bot arrêté."
else
    echo "Le bot n'est pas en cours d'exécution."
fi
EOF

            chmod +x "$STOP_SCRIPT"

            echo -e "${GREEN}Scripts daemon créés :${NC}"
            echo " - Pour démarrer : $DAEMON_SCRIPT"
            echo " - Pour arrêter : $STOP_SCRIPT"

            echo -e "${YELLOW}Voulez-vous démarrer le daemon maintenant ? (o/n)${NC}"
            read -p "Démarrer maintenant [o] : " START_DAEMON
            START_DAEMON=${START_DAEMON:-o}

            if [[ $START_DAEMON == "o" || $START_DAEMON == "O" ]]; then
                bash "$DAEMON_SCRIPT"
                echo -e "${GREEN}Daemon démarré.${NC}"
            fi

            echo -e "${YELLOW}Pour que le bot démarre automatiquement au démarrage du NAS,"
            echo -e "ajoutez une tâche dans le Planificateur de tâches DSM avec la commande :${NC}"
            echo "$DAEMON_SCRIPT"
            ;;
    esac

    if [ "$TASK_FREQUENCY" != "4" ]; then
        echo -e "${YELLOW}Pour ajouter cette tâche planifiée, suivez ces étapes :${NC}"
        echo "1. Ouvrez DSM > Panneau de configuration > Planificateur de tâches"
        echo "2. Cliquez sur Créer > Tâche planifiée > Script défini par l'utilisateur"
        echo "3. Configurez la tâche avec l'expression cron : $CRON_EXPRESSION"
        echo "4. Dans le champ 'Exécuter la commande', entrez : $COMMAND"
        echo "5. Cliquez sur OK pour enregistrer"
    fi
fi

# 8. Configuration des clés API
echo -e "${YELLOW}Souhaitez-vous configurer vos clés API Binance maintenant ? (o/n)${NC}"
read -p "Configurer les clés API [o] : " CONFIGURE_API
CONFIGURE_API=${CONFIGURE_API:-o}

if [[ $CONFIGURE_API == "o" || $CONFIGURE_API == "O" ]]; then
    echo -e "${YELLOW}Veuillez entrer votre clé API Binance :${NC}"
    read -p "Clé API : " API_KEY

    echo -e "${YELLOW}Veuillez entrer votre clé secrète Binance :${NC}"
    read -p "Clé secrète : " API_SECRET

    # Modifier le fichier de configuration
    CONFIG_FILE="$INSTALL_PATH/config/config.php"

    if [ -f "$CONFIG_FILE" ]; then
        sed -i "s/'key' => 'VOTRE_CLE_API_BINANCE'/'key' => '$API_KEY'/g" "$CONFIG_FILE"
        sed -i "s/'secret' => 'VOTRE_SECRET_API_BINANCE'/'secret' => '$API_SECRET'/g" "$CONFIG_FILE"
        echo -e "${GREEN}Configuration API mise à jour.${NC}"
    else
        echo -e "${RED}Fichier de configuration non trouvé : $CONFIG_FILE${NC}"
        echo "Veuillez configurer manuellement vos clés API."
    fi
fi

# 9. Instructions finales
echo ""
echo -e "${GREEN}=== Installation terminée ===${NC}"
echo ""
echo -e "${YELLOW}Votre bot de trading Binance est installé dans :${NC} $INSTALL_PATH"
echo ""
echo -e "${YELLOW}Commandes utiles :${NC}"
echo " - Pour lancer le backtesting : php $INSTALL_PATH/backtest.php"
echo " - Pour exécuter le bot manuellement : php $INSTALL_PATH/run.php"
echo " - Pour utiliser une stratégie spécifique : php $INSTALL_PATH/run.php RSIStrategy"
echo ""
echo -e "${YELLOW}Vérifiez et modifiez la configuration dans :${NC} $INSTALL_PATH/config/config.php"
echo -e "${YELLOW}Modifiez les paramètres des stratégies dans :${NC} $INSTALL_PATH/config/strategies.php"
echo ""
echo -e "${GREEN}Bon trading !${NC}"
