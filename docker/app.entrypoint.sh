#!/bin/bash

# Run app setup script.
. app-setup.sh
. app-post-setup.sh

exec "$@"
