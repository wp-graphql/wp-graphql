#!/usr/bin/env python3
"""
A/B runner for the node-scoped global state change (wp-graphql#4572 / #4575).

Runs a directory of GraphQL queries against a WPGraphQL endpoint and saves each response, then
diffs two saved runs query by query.

Usage:
  matrix.py run  --queries=<dir> --out=<dir> --label=<build> [--endpoint=URL] [--ids=<file>]
  matrix.py diff --queries=<dir> --out=<dir> --a=<build> --b=<build>

Query files: <name>.graphql, with optional <name>.vars.json. Variable values written as
"$NAME" are replaced from the ids file (KEY=VALUE lines, as written by the fixture scripts).
"""

import argparse
import difflib
import json
import os
import re
import sys
import urllib.request


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("command", choices=["run", "diff"])
    parser.add_argument("--queries", required=True)
    parser.add_argument("--out", required=True)
    parser.add_argument("--label")
    parser.add_argument("--endpoint", default="http://localhost:8888/graphql")
    parser.add_argument("--ids")
    parser.add_argument("--a")
    parser.add_argument("--b")
    return parser.parse_args()


def load_ids(path):
    ids = {}
    if path and os.path.exists(path):
        with open(path) as handle:
            for line in handle:
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    key, value = line.split("=", 1)
                    ids[key.strip()] = value.strip()
    return ids


def substitute(value, ids):
    if isinstance(value, str):
        match = re.fullmatch(r"\$([A-Z0-9_]+)", value)
        if match:
            if match.group(1) not in ids:
                raise KeyError(f"no id named {match.group(1)}")
            raw = ids[match.group(1)]
            return int(raw) if raw.isdigit() else raw
        return value
    if isinstance(value, list):
        return [substitute(item, ids) for item in value]
    if isinstance(value, dict):
        return {key: substitute(item, ids) for key, item in value.items()}
    return value


def query_names(directory):
    return sorted(name[: -len(".graphql")] for name in os.listdir(directory) if name.endswith(".graphql"))


def run(args):
    ids = load_ids(args.ids)
    target = os.path.join(args.out, args.label)
    os.makedirs(target, exist_ok=True)

    for name in query_names(args.queries):
        with open(os.path.join(args.queries, name + ".graphql")) as handle:
            query = handle.read()

        variables = {}
        vars_path = os.path.join(args.queries, name + ".vars.json")
        if os.path.exists(vars_path):
            with open(vars_path) as handle:
                variables = substitute(json.load(handle), ids)

        request = urllib.request.Request(
            args.endpoint,
            data=json.dumps({"query": query, "variables": variables}).encode(),
            headers={"Content-Type": "application/json"},
        )
        try:
            with urllib.request.urlopen(request, timeout=120) as response:
                body = response.read().decode()
        except urllib.error.HTTPError as error:
            body = error.read().decode()

        try:
            result = json.loads(body)
            # Debug and cache metadata differ between runs for reasons unrelated to the change.
            if isinstance(result, dict):
                result.pop("extensions", None)
            text = json.dumps(result, indent=2, sort_keys=True)
        except ValueError:
            text = "NON-JSON RESPONSE\n" + body

        with open(os.path.join(target, name + ".json"), "w") as handle:
            handle.write(text + "\n")

        status = "errors" if '"errors"' in text else "ok"
        print(f"{args.label:>10}  {name:<50} {status}")


def diff(args):
    differing = 0
    for name in query_names(args.queries):
        paths = [os.path.join(args.out, label, name + ".json") for label in (args.a, args.b)]
        if not all(os.path.exists(path) for path in paths):
            print(f"MISSING    {name}")
            continue

        texts = []
        for path in paths:
            with open(path) as handle:
                texts.append(handle.read().splitlines())

        if texts[0] == texts[1]:
            print(f"same       {name}")
            continue

        differing += 1
        print(f"DIFFERENT  {name}")
        for line in difflib.unified_diff(texts[0], texts[1], args.a, args.b, n=1, lineterm=""):
            print("    " + line[:240])

    print(f"\n{differing} of {len(query_names(args.queries))} queries differ between {args.a} and {args.b}")


if __name__ == "__main__":
    arguments = parse_args()
    (run if arguments.command == "run" else diff)(arguments)
    sys.exit(0)
