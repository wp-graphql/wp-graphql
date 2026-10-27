#!/bin/zsh
# Usage: zsh bin/run.sh <group> <label> <graphql-endpoint>
#
# Runs every query in the group's query directories against the endpoint and saves the responses
# under out/<query-dir>/<label>/, then normalizes them. Run it once per WPGraphQL build with a
# different label, then compare two builds with:
#   python3 tools/matrix.py diff --queries=queries/<query-dir> --out=out/<query-dir> --a=<label> --b=<label>

source "$(dirname "$0")/lib.sh"
GROUP=$1 LABEL=$2 ENDPOINT=$3
require_group "$GROUP"
if [[ -z $LABEL || -z $ENDPOINT ]]; then
	echo "Usage: zsh bin/run.sh <group> <label> <graphql-endpoint>" >&2
	exit 1
fi

for dir in ${=GROUP_QUERIES[$GROUP]}; do
	python3 -I "$ROOT/tools/matrix2.py" run --queries="$ROOT/queries/$dir" --out="$ROOT/out/$dir" \
		--label="$LABEL" --ids="$ROOT/fixtures/$GROUP.ids" --endpoint="$ENDPOINT"
	python3 -I "$ROOT/tools/normalize.py" "$ROOT/out/$dir/$LABEL"
done
