vendor/%: composer.json
	composer install --optimize-autoloader

phpstan.neon: phpstan.dist.neon
	cp $< $@

phpstan: vendor/bin/phpstan phpstan.neon
	php $< analyse
.PHONY: phpstan

up: compose.yaml
	docker compose up --detach
.PHONY: up