<?php
// Shared simox host-hardening declaration (firewall reconcile data). Consumed
// by the framework gen-firewall: the CLI reads this package's default.php,
// computes the desired ufw rule set for each [prod] host from its
// etc/machines.ini tags, and applies it as root (fail-open: SSH first,
// enable last). There is no SQL here — this package is data only.
//
// The framework owns the mechanism and the built-in tags; this file carries
// only consumer data (no tag name, port, range or endpoint is hardcoded in
// the framework):
//
//   $zerotierRange = '10.147.x.0/24';
//   $cloudTest     = array('endpoint' => 'http://169.254.169.254/latest/meta-data/',
//                          'timeout' => 3);
//   $tagRules      = array(
//       'web'   => array('80/tcp', '443/tcp'),
//       'cloud' => array(array('rule' => '9993/udp', 'gate' => 'cloud')),
//   );
//
// $zerotierRange scopes the baseline SSH and the built-in db:<name> allows.
// A concrete CIDR is used verbatim; an 'x' template (like the one below)
// resolves its private third octet from the consumer's private etc/hosts, so
// the real /24 lives in the private config and is never committed here.
//
// $cloudTest is the runtime metadata probe that gates gate=>'cloud' rules.
// Any HTTP response (even a 404) counts as cloud; no response/timeout means
// not-cloud (fail-safe: the gated rule is skipped, not the whole host).
//
// $tagRules maps a consumer-owned tag to its allow rules. The baseline
// (default deny incoming / allow outgoing; allow 22/tcp from the ZeroTier
// range) and the built-in db:<name> (allow <reuter.ini PORT>/tcp from the
// range) and worker (no inbound) tags are framework-owned and never listed
// here. Tags only ADD allow rules; the baseline owns the defaults and any
// denies.

$zerotierRange = '10.147.x.0/24';

$cloudTest = array(
    'endpoint' => 'http://169.254.169.254/latest/meta-data/',
    'timeout' => 3,
);

$tagRules = array(
    'web'   => array('80/tcp', '443/tcp'),
    'cloud' => array(array('rule' => '9993/udp', 'gate' => 'cloud')),
);
?>
