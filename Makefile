export DOCKER_API_VERSION=1.41

migrate:
	./vendor/bin/sail artisan migrate
