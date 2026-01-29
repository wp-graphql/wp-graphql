vcl 4.1;

import xkey;

backend default {
    .host = "app";
    .port = "80";
}

sub vcl_deliver {
	if (obj.hits > 0) {
		set resp.http.X-Cache = "HIT: " + obj.hits;
	} else {
		set resp.http.X-Cache = "MISS";
	}
}

sub vcl_recv {

    set req.http.host = "localhost:8091";

    if (req.method == "PURGE_GRAPHQL") {
        set req.http.n-gone = 0;

        if (req.http.GraphQL-Purge-Keys && req.http.GraphQL-URL ) {
            set req.http.xkeyPrefix = req.http.GraphQL-URL;

            # replace commas with spaces
            set req.http.cleanPurgeKeys = regsuball(req.http.GraphQL-Purge-Keys, "(\,+),?", " ");

            # find all strings separated by space and add the host as a prefix.
            set req.http.purgeKeys = regsuball(req.http.cleanPurgeKeys, "(\S+),?", req.http.xkeyPrefix + ":\1");

            # call xkey.purge on the keys sent in the GraphQL-Purge-Keys header(s)
            set req.http.n-gone = xkey.purge( req.http.purgeKeys );
        }

        # Return 200, reason showing how many queries were invalidated
        return (synth(200, "Invalidated GraphQL Queries: "+req.http.purgeKeys+" "+req.http.n-gone));
    }
}

sub vcl_backend_response {
	if ( beresp.http.X-GraphQL-Keys ) {

        set beresp.http.xkeyPrefix = beresp.http.X-GraphQL-URL;
        set beresp.http.xkey = regsuball(beresp.http.X-GraphQL-Keys, "(\S+),?", beresp.http.xkeyPrefix + ":\1");
	}
}
