#!/usr/bin/env python3
"""Normalize known nondeterministic content in saved responses, in place.
Usage: normalize.py <results-dir>   (idempotent: safe to run more than once)
- WooCommerce quantity input ids: quantity_<uniqid> -> quantity_UNIQID
- WooCommerce "Related products" section (randomly shuffled by wc_get_related_products):
  replaced by the sorted list of the product ids it contains, so membership changes still show.
- JWTs (Headless Login authToken; they embed iat/exp timestamps) -> <JWT>"""
import json, os, re, sys
QTY = re.compile(r"quantity_[0-9a-f]{13}")
JWT = re.compile(r"\beyJ[\w-]+\.[\w-]+\.[\w-]+")
REL = re.compile(r'<section class="related products">.*?</section>', re.S)

def rel(m):
    # Already normalized: leave it, so running this twice gives the same result.
    if "[normalized: related product ids" in m.group(0):
        return m.group(0)
    ids = sorted(set(int(x) for x in re.findall(r"\bpost-(\d+) status-", m.group(0))))
    return '<section class="related products">[normalized: related product ids %s]</section>' % ids

def norm(v):
    if isinstance(v, str):
        return JWT.sub("<JWT>", REL.sub(rel, QTY.sub("quantity_UNIQID", v)))
    if isinstance(v, list):
        return [norm(x) for x in v]
    if isinstance(v, dict):
        return {k: norm(x) for k, x in v.items()}
    return v

n = 0
for root, _, files in os.walk(sys.argv[1]):
    for f in files:
        if not f.endswith(".json"):
            continue
        p = os.path.join(root, f); s = open(p).read()
        try:
            t = json.dumps(norm(json.loads(s)), indent=2, sort_keys=True) + "\n"
        except ValueError:
            continue
        if t != s:
            open(p, "w").write(t); n += 1
print(f"normalized {n} files")
