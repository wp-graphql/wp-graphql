/**
 * k6: load persisted PQC GET URLs (edge or origin).
 *
 * urls.txt: one path per line, e.g. /graphql/persisted/abc... or full paths only (no host).
 *
 * Bundled Varnish sets X-Cache: "HIT: N" or "MISS" (see ../docker/varnish/default.vcl).
 *
 *   k6 run pqc-persisted-get.js \
 *     -e BASE_URL=http://localhost:8081 \
 *     -e URLS_FILE=urls.txt
 *
 * Optional: require minimum edge hit rate (0–1), e.g. after warm-up:
 *   -e MIN_HIT_RATE=0.85
 *
 * Optional: fail if X-Cache header missing (use on Varnish edge only):
 *   -e REQUIRE_X_CACHE=1
 *
 * Origin baseline (BASE_URL :8888): omit REQUIRE_X_CACHE; X-Cache is usually absent → pqc_x_cache_unknown.
 */

import http from "k6/http";
import { check } from "k6";
import { Counter, Rate, Trend } from "k6/metrics";
import { SharedArray } from "k6/data";

const baseUrl = __ENV.BASE_URL || "http://localhost:8081";
const urlsFile = __ENV.URLS_FILE || "urls.txt";

const duration = __ENV.DURATION || "2m";
const vus = Number(__ENV.VUS || 10);

const responseTime = new Trend("pqc_get_duration", true);
const xCacheHits = new Counter("pqc_x_cache_hits");
const xCacheMisses = new Counter("pqc_x_cache_misses");
const xCacheUnknown = new Counter("pqc_x_cache_unknown");
const xCacheHitRate = new Rate("pqc_x_cache_hit_rate");

/**
 * Classify Varnish-style X-Cache (case-insensitive).
 *
 * @param {string} raw Header value or "".
 * @returns {"hit"|"miss"|"unknown"}
 */
function classifyXCache(raw) {
	const s = String(raw || "")
		.trim()
		.toUpperCase();
	if (s.length === 0) {
		return "unknown";
	}
	if (s.startsWith("HIT")) {
		return "hit";
	}
	if (s.startsWith("MISS")) {
		return "miss";
	}
	return "unknown";
}

function buildThresholds() {
	const t = {
		http_req_failed: ["rate<0.05"],
	};
	const minRaw = __ENV.MIN_HIT_RATE;
	if (minRaw !== undefined && String(minRaw).length > 0) {
		const n = parseFloat(String(minRaw));
		if (!isNaN(n) && n > 0 && n <= 1) {
			t.pqc_x_cache_hit_rate = [`rate>=${n}`];
		}
	}
	return t;
}

const paths = new SharedArray("urls", function () {
	// k6 open() is relative to the current working directory when you run k6.
	const raw = open(urlsFile);
	return raw
		.split("\n")
		.map((line) => line.trim())
		.filter((line) => line.length > 0 && !line.startsWith("#"));
});

export const options = {
	vus: vus,
	duration: duration,
	thresholds: buildThresholds(),
};

export default function () {
	if (paths.length === 0) {
		return;
	}
	const path = paths[Math.floor(Math.random() * paths.length)];
	const url = path.startsWith("http") ? path : `${baseUrl.replace(/\/$/, "")}${path.startsWith("/") ? "" : "/"}${path}`;

	const res = http.get(url, {
		headers: { Accept: "application/json" },
	});

	if (res.timings) {
		responseTime.add(res.timings.duration);
	}

	const headers = res.headers || {};
	const xc = headers["X-Cache"] || headers["x-cache"] || "";
	const kind = classifyXCache(xc);

	if (kind === "hit") {
		xCacheHits.add(1);
		xCacheHitRate.add(true);
	} else if (kind === "miss") {
		xCacheMisses.add(1);
		xCacheHitRate.add(false);
	} else {
		xCacheUnknown.add(1);
		xCacheHitRate.add(false);
	}

	const checks = {
		"status 200": (r) => r.status === 200,
	};
	if (__ENV.REQUIRE_X_CACHE === "1" || __ENV.REQUIRE_X_CACHE === "true") {
		checks["x-cache present"] = () => xc.length > 0;
	}
	check(res, checks);
}
