SHELL := /bin/bash

up:
	docker compose up -d

down:
	docker compose down -v

keygen:
	@mkdir -p var/keys
	@[ -f var/keys/private.pem ] || openssl genrsa -out var/keys/private.pem 4096
	@chmod 600 var/keys/private.pem
	openssl rsa -in var/keys/private.pem -pubout -out var/keys/public.pem
	@KID=$$(uuidgen || cat /proc/sys/kernel/random/uuid); \
	  (grep -q '^JWT_KID=' .env.local 2>/dev/null && sed -i '' -E "s/^JWT_KID=.*/JWT_KID=$${KID}/" .env.local || echo "JWT_KID=$${KID}" >> .env.local); \
	  echo Generated KID: $${KID}

migrate:
	php bin/console doctrine:database:create --if-not-exists || true
	php bin/console doctrine:migrations:migrate -n

test:
	composer test

seed:
	php bin/console app:seed-demo

# Linting and Code Quality
phpstan:
	./vendor/bin/phpstan analyse

phpstan-baseline:
	./vendor/bin/phpstan analyse --generate-baseline

check: phpstan test

ci: check
