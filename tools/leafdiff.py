#!/usr/bin/env python3
"""Leaf-level diff of two saved responses. Usage: leafdiff.py <results-dir> <query-name> <a> <b>
Embedded JSON strings (schema raw) are parsed; long strings are shown as a window around the first difference."""
import json, os, sys

def flat(v, p=""):
    if isinstance(v, str) and v[:1] in "{[":
        try:
            v = json.loads(v)
        except ValueError:
            pass
    if isinstance(v, dict):
        for k in sorted(v):
            yield from flat(v[k], f"{p}.{k}")
    elif isinstance(v, list):
        for i, x in enumerate(v):
            yield from flat(x, f"{p}[{i}]")
    else:
        yield p, v

def window(a, b, n=70):
    if not (isinstance(a, str) and isinstance(b, str)) or (len(a) < 2 * n and len(b) < 2 * n):
        return repr(a), repr(b)
    i = 0
    while i < min(len(a), len(b)) and a[i] == b[i]:
        i += 1
    s = max(0, i - 25)
    return "…" + repr(a[s:s + n]) + "…", "…" + repr(b[s:s + n]) + "…"

d, q, A, B = sys.argv[1:5]
ja = dict(flat(json.load(open(os.path.join(d, A, q + ".json")))))
jb = dict(flat(json.load(open(os.path.join(d, B, q + ".json")))))
for k in sorted(set(ja) | set(jb)):
    va, vb = ja.get(k, "<absent>"), jb.get(k, "<absent>")
    if va != vb:
        x, y = window(va, vb)
        print(f"{k}\n   {A}: {x}\n   {B}: {y}")
