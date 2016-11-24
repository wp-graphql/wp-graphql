var urllib = require("url");

module.exports.CookieJar = CookieJar;

function CookieJar(options) {
    this.options                = options || {};
    this.options.sessionTimeout = this.options.sessionTimeout || 1800; // 30min

    this.cookies = {};
}

CookieJar.prototype.addCookie = function (cookie) {

    if (!cookie || !cookie.name) return;

    var lcookie;

    if (!this.cookies[cookie.name]) {
        this.cookies[cookie.name] = [];
    }

    // overwrite if has same params
    for (var i = 0, len = this.cookies[cookie.name].length; i < len; i++) {
        lcookie = this.cookies[cookie.name][i];
        if (
            lcookie.path == cookie.path &&
            lcookie.domain == cookie.domain &&
            lcookie.secure == cookie.secure &&
            lcookie.httponly == cookie.httponly
        ) {
            this.cookies[cookie.name][i] = cookie;
            return;
        }
    }

    this.cookies[cookie.name].push(cookie);
}

CookieJar.prototype.getCookies = function (url) {
    var keys            = Object.keys(this.cookies),
        cookie, cookies = [];

    for (var i = 0, len = keys.length; i < len; i++) {
        if (Array.isArray(this.cookies[keys[i]])) {
            for (var j = 0, lenj = this.cookies[keys[i]].length; j < lenj; j++) {
                cookie = this.cookies[keys[i]][j];
                if (this.matchCookie(cookie, url)) {
                    cookies.push(cookie.name + "=" + cookie.value);
                }
            }
        }
    }
    return cookies.join("; ");
}

CookieJar.prototype.matchCookie = function (cookie, url) {
    var urlparts = urllib.parse(url || "", false, true),
        path;

    // check expire
    if (cookie.expire) {
        if (cookie.expire.getTime() < Date.now()) {
            return;
        }
    }

    // check if hostname matches
    if (urlparts.hostname && cookie._domain) {
        if (!(urlparts.hostname == cookie._domain || urlparts.hostname.substr(-(cookie._domain.length + 1)) == "." + cookie._domain)) {
            return false;
        }
    }

    // check if path matches
    if (cookie.path && urlparts.pathname) {

        path = (urlparts.pathname || "/").split("/");
        path.pop();
        path = path.join("/").trim();
        if (path.substr(0, 1) != "/") {
            path = "/" + path;
        }
        if (path.substr(-1) != "/") {
            path += "/";
        }

        if (path.substr(0, cookie.path.length) != cookie.path) {
            return false;
        }
    }

    // check secure
    if (cookie.secure && urlparts.protocol) {
        if (urlparts.protocol != "https:") {
            return false;
        }
    }

    // check httponly
    if (cookie.httponly && urlparts.protocol) {
        if (urlparts.protocol != "http:") {
            return false;
        }
    }

    return true;
}

CookieJar.prototype.setCookie = function (cookie_str, url) {
    var parts    = (cookie_str || "").split(";"),
        cookie   = {},
        urlparts = urllib.parse(url || "", false, true),
        path;

    parts.forEach((function (part) {
        var key, val;
        part = part.split("=");
        key  = part.shift().trim();
        val  = part.join("=").trim();

        if (!key)return;

        switch (key.toLowerCase()) {

            case "expires":

                cookie.expires = new Date(val);
                break;

            case "path":
                cookie.path = val.trim();
                break;

            case "domain":
                cookie.domain = val.toLowerCase();
                break;

            case "max-age":
                cookie.expires = new Date(Date.now() + (Number(val) || 0) * 1000);
                break;

            case "secure":
                cookie.secure = true;
                break;

            case "httponly":
                cookie.httponly = true;
                break;

            default:
                if (!cookie.name) {
                    cookie.name  = key;
                    cookie.value = val;
                }
        }
    }).bind(this));

    // use current path when path is not specified
    if (!cookie.path) {
        path = (urlparts.pathname || "/").split("/");
        path.pop();
        cookie.path = path.join("/").trim();
        if (cookie.path.substr(0, 1) != "/") {
            cookie.path = "/" + cookie.path;
        }
        if (cookie.path.substr(-1) != "/") {
            cookie.path += "/";
        }
    }

    // if no expire date, then use sessionTimeout value
    if (!cookie.expires) {
        cookie._expires = new Date(Date.now() + (Number(this.options.sessionTimeout) || 0) * 1000);
    } else {
        cookie._expires = cookie.expires;
    }

    if (!cookie.domain) {
        if (urlparts.hostname) {
            cookie._domain = urlparts.hostname;
        }
    } else {
        cookie._domain = cookie.domain;
    }

    this.addCookie(cookie);
}
