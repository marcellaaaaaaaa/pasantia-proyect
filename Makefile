export DOCKER_API_VERSION=1.41

.PHONY: migrate
migrate:
	./vendor/bin/sail artisan migrate
