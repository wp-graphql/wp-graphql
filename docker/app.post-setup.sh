#!/bin/bash

# Activate wp-graphql
wp plugin activate wp-graphql --allow-root

# Set pretty permalinks.
wp rewrite structure '/%year%/%monthnum%/%postname%/' --allow-root

wp db export "${DATA_DUMP_DIR}/dump.sql" --allow-root

# If maintenance mode is active, de-activate it
wp maintenance-mode deactivate
