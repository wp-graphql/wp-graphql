/**
 * k6: load persisted PQC GET URLs through the edge.
 *
 * urls.txt: one path per line, e.g. /graphql/persisted/abc... or full paths only (no host).
 *
 *   k6 run pqc-persisted-get.js \
 *     -e BASE_URL=http://localhost:8081 \
 *     -e URLS_FILE=urls.txt
 */

import http from "k6/http";
import { check, trend } from "k6/metrics";
import { SharedArray } from "k6/data";

const baseUrl = __ENV.BASE_URL || "http://localhost:8081";
const urlsFile = __ENV.URLS_FILE || "urls.txt";

const duration = __ENV.DURATION || "2m";
const vus = Number(__ENV.VUS || 10);

const responseTime = new trend("pqc_get_duration", true);

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
	thresholds: {
		http_req_failed: ["rate<0.05"],
	},
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

	responseTime.add(res.timings.duration);

	const xc = res.headers["X-Cache"] || res.headers["x-cache"] || "";
	check(res, {
		"status 200": (r) => r.status === 200,
		"x-cache present": () => xc.length > 0,
	});
}
