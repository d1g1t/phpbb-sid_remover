# SID Remover

## The Problem
phpBB automatically adds `sid=` (session IDs) to URLs whenever a new user visits the forums visits the forum for the first time (until cookies have been set or the user agent matches a known bot as configured).

When a badly written crawler, one that doesn't identify itself as a bot/doesn't respect robots.txt/doesn't accept session cookies/doesn't learn from canonical links, visits, unique urls are generated for each visit.
This causes the bots to believe there are more links that need to be crawled, creating a cycle of infinite crawling of infinite urls.

This behaviour can cause a [DDoSes like effect](https://www.phpbb.com/community/viewtopic.php?t=2662519)

[phpBB does not plan to fix this in the current version of the software](https://tracker.phpbb.com/browse/PHPBB-17502?focusedId=66700&page=com.atlassian.jira.plugin.system.issuetabpanels:comment-tabpanel#comment-66700)

## What this extension does

Prevents SIDs from being added to urls while a user is not logged in.

These are simply changes discussed on [the support forum](https://www.phpbb.com/community/viewtopic.php?t=2659822) packaged as an extension to avoid editing core files.

## Will this stop the bots?

No. It does **not** stop bots entirely, but it stops giving them new URLs to crawl.
If these crawlers have already saved a large number of such previously generated urls, they will still try to crawl them at least once.
Once the site stops generating more URLs with `sid`s, the bad bot traffic should automatically decrease.

## Dealing with an ongoing attack

I use the following Varnish VCL to redirect links containing `sid`s to a version without them.
This allows you to leverage any upstream caching you have in place such as Varnish or even Cloudflare.

phpBB requires certain urls to have sids, so it cannot be removed from all urls
```vcl
sub vcl_recv {
    if (req.url ~ "(p|f|t)=[^&]+&sid=[^&]+") {
        set req.url = regsub(req.url, "&sid=[^&]+", "");
        return (synth(750, ""));
    }
    if (req.url ~ "sid=[^&]+&(p|f|t|start)=[^&]+") {
        set req.url = regsub(req.url, "sid=[^&]+&", "");
        return (synth(750, ""));
    }
    if (req.url ~ "(viewprofile|register|post|privacy|resend_act|terms|index).*&sid=[^&]+") {
        set req.url = regsub(req.url, "&sid=[^&]+", "");
        return (synth(750, ""));
    }
    if (req.url ~ "^(help\/faq|user\/delete_cookies|user\/forgot_password)\?sid=[^&]+") {
        set req.url = regsub(req.url, "\?sid=[^&]+", "");
        return (synth(750, ""));
    }
    # Existing Config...
}

sub vcl_synth {
    # Existing Config...
    if (resp.status == 750) {
        set resp.status = 301;
        set resp.http.Location = req.url;
        return (deliver);
    }
}
```

I had Gemini convert this to an `.htaccess` format. It looks like it should work, but I've not tested it.
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    # Rule 1: Matches p, f, or t followed by &sid=
    RewriteCond %{QUERY_STRING} ^(p|f|t)=[^&]+&sid=[^&]+$ [NC]
    RewriteRule .* %{REQUEST_URI}? [R=301,L]

    # Rule 2: Matches &sid= followed by p, f, t, or start
    RewriteCond %{QUERY_STRING} ^sid=[^&]+&(p|f|t|start)=[^&]+$ [NC]
    RewriteRule .* %{REQUEST_URI}? [R=301,L]

    # Rule 3: Matches specific pages followed by &sid=
    RewriteCond %{REQUEST_URI} ^/(viewprofile|register|post|privacy|resend_act|terms|index)$ [NC]
    RewriteCond %{QUERY_STRING} ^.*&sid=[^&]+$ [NC]
    RewriteRule .* %{REQUEST_URI}? [R=301,L]

    # Rule 4: Matches help/faq, user/delete_cookies, or user/forgot_password followed by ?sid=
    RewriteCond %{REQUEST_URI} ^/(help/faq|user/delete_cookies|user/forgot_password)$ [NC]
    RewriteCond %{QUERY_STRING} ^sid=[^&]+$ [NC]
    RewriteRule .* %{REQUEST_URI}? [R=301,L]
</IfModule>

**The above changes are not required for the extension to work.**
```

## Installation

Copy the extension to `phpBB/ext/d1g1t/sid_remover`

Go to "ACP" > "Customise" > "Extensions" and enable the "SID Remover" extension.

## Removal

This extension can be disabled/removed at any time without any issues.

## License

[GPLv2](license.txt)
