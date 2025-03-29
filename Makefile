vendor/%: composer.json
	composer install --optimize-autoloader

phpstan.neon: phpstan.dist.neon
	cp $< $@

phpstan: vendor/bin/phpstan phpstan.neon
	php $< analyse
.PHONY: phpstan

fixcs: vendor/bin/php-cs-fixer
	php vendor/bin/php-cs-fixer fix src
.PHONY: fixcs

up: compose.yaml
	docker compose up --detach
.PHONY: up

acl:
	sudo chmod 777 -R ./config
	sudo chmod 777 -R ./data
	sudo chmod 777 -R ./logs
.PHONY: acl

config/config.php: config/config.default.php
	cp $< $@
