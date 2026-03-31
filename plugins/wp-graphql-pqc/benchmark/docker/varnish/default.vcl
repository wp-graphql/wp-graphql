vcl 4.1;

# Bench stack: Varnish in Docker → WordPress on the host (e.g. wp-env :8888).
# Clients use http://localhost:8081/... so the edge caches by full URL (PQC paths).

backend default {
    .host = "host.docker.internal";
    .port = "8888";
}

sub vcl_recv {
    # Match the Host header wp-env expects.
    set req.http.Host = "localhost:8888";

    if (req.method == "PURGE") {
        return (purge);
    }

    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    return (hash);
}

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT: " + obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }
}
