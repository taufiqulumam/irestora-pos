.PHONY: help dev build test lint typecheck docker-up docker-down backend-install backend-migrate backend-test

help:
	@echo "iRestora POS - Available Commands:"
	@echo ""
	@echo "Development:"
	@echo "  make dev           - Start all services (backend + 3 frontends)"
	@echo "  make backend       - Start Laravel backend only"
	@echo "  make cashier       - Start Cashier PWA dev server"
	@echo "  make customer      - Start Customer Web dev server"
	@echo "  make admin         - Start Admin Panel dev server"
	@echo ""
	@echo "Build:"
	@echo "  make build         - Build all frontend apps"
	@echo "  make build-cashier - Build Cashier PWA"
	@echo "  make build-customer - Build Customer Web"
	@echo "  make build-admin   - Build Admin Panel"
	@echo ""
	@echo "Testing & Quality:"
	@echo "  make test          - Run all tests"
	@echo "  make lint          - Run linters (ESLint + Pint)"
	@echo "  make typecheck     - Run TypeScript type checking"
	@echo ""
	@echo "Backend:"
	@echo "  make backend-install  - Install PHP dependencies"
	@echo "  make backend-migrate  - Run database migrations"
	@echo "  make backend-test     - Run PHPUnit tests"
	@echo "  make backend-seed     - Run database seeders"
	@echo ""
	@echo "Docker:"
	@echo "  make docker-up     - Start all containers (production-like)"
	@echo "  make docker-down   - Stop all containers"
	@echo "  make docker-logs   - View container logs"
	@echo ""
	@echo "Types:"
	@echo "  make generate-types - Generate TS types from OpenAPI spec"

dev:
	npm run dev

backend:
	cd backend && php artisan serve

cashier:
	npm run dev --workspace=frontend/cashier

customer:
	npm run dev --workspace=frontend/customer

admin:
	npm run dev --workspace=frontend/admin

build:
	npm run build

build-cashier:
	npm run build --workspace=frontend/cashier

build-customer:
	npm run build --workspace=frontend/customer

build-admin:
	npm run build --workspace=frontend/admin

test:
	npm run test

lint:
	npm run lint

typecheck:
	npm run typecheck

generate-types:
	npm run generate:types

docker-up:
	docker compose -f docker/docker-compose.yml up -d

docker-down:
	docker compose -f docker/docker-compose.yml down

docker-logs:
	docker compose -f docker/docker-compose.yml logs -f

backend-install:
	cd backend && composer install

backend-migrate:
	cd backend && php artisan migrate

backend-test:
	cd backend && php artisan test

backend-seed:
	cd backend && php artisan db:seed