SHELL:=/usr/bin/env bash

.DEFAULT_GOAL:=help

DOCKER_VERSIONS_FILE:=docker-tasks/common/env/docker-versions.sh

.PHONY: help
help:
# Stole this nasty bit of script from here: https://marmelab.com/blog/2016/02/29/auto-documented-makefile.html
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: test-environment
test-environment: ## Starts a provisioned Dockerized environment for running tests
	@source "${DOCKER_VERSIONS_FILE}" \
	  && docker-tasks/test-environment/bin/run.sh

.PHONY: test-environment-shell
test-environment-shell: ## Logs into running Docker test environment shell.
	@docker-tasks/common/bin/docker-shell.sh 'wpgraphql-tester'

.PHONY: tests
tests: ## runs unit/integration tests. Example: make tests TEST_TYPE='wpunit'
	@source "${DOCKER_VERSIONS_FILE}" \
	  && env TEST_TYPE="${TEST_TYPE}" docker-tasks/tests/bin/run.sh "${PWD}/docker-output/${TEST_TYPE}"

.PHONY: local-app
local-app: ## Runs WordPress + wp-graphql locally.
	@source ${DOCKER_VERSIONS_FILE} \
	  && docker-tasks/local-app/bin/run.sh

.PHONY: local-app-xdebug
local-app-xdebug: ## Runs WordPress + wp-graphql + XDebug locally.
	@source ${DOCKER_VERSIONS_FILE} \
	  && env USE_XDEBUG='true' docker-tasks/local-app/bin/run-xdebug.sh
