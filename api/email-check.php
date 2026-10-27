<?php
declare(strict_types=1);

/*
 * Email check for the lead form. PHP copy of assets/email-check.js — keep the
 * rules and lists identical. The server does not block 'typo' (the browser
 * asks once and lets the visitor confirm); it does check the domain has mail.
 *
 * check_email($raw) → ['ok' => bool, 'email' => string, 'reason' => string, 'suggestion' => string]
 */

const EMAIL_MESSAGES = [
    "format" => "Inserisci un indirizzo e-mail valido.",
    "typo" => "Intendevi {email}?",
    "disposable" => "Gli indirizzi e-mail temporanei non sono accettati: usa la tua e-mail personale.",
    "fake" => "Inserisci il tuo vero indirizzo e-mail.",
    "domain" => "Questo dominio e-mail non esiste: controlla l'indirizzo.",
];
const EMAIL_KNOWN = [
    "gmail.com",
    "googlemail.com",
    "hotmail.com",
    "hotmail.it",
    "hotmail.es",
    "hotmail.co.uk",
    "outlook.com",
    "outlook.it",
    "outlook.es",
    "live.com",
    "live.it",
    "live.co.uk",
    "msn.com",
    "yahoo.com",
    "yahoo.it",
    "yahoo.es",
    "yahoo.co.uk",
    "ymail.com",
    "icloud.com",
    "me.com",
    "mac.com",
    "libero.it",
    "virgilio.it",
    "alice.it",
    "tim.it",
    "tin.it",
    "tiscali.it",
    "fastwebnet.it",
    "email.it",
    "inwind.it",
    "iol.it",
    "aruba.it",
    "pec.it",
    "legalmail.it",
    "gmx.com",
    "gmx.es",
    "gmx.it",
    "aol.com",
    "protonmail.com",
    "proton.me",
    "telefonica.net",
    "movistar.es",
    "btinternet.com",
    "sky.com",
    "virginmedia.com",
    "talktalk.net",
    "ntlworld.com",
    "mail.com",
    "email.com",
    "gmail.it",
    "hotmail.fr",
    "outlook.fr",
    "live.fr",
    "yahoo.fr",
    "orange.fr",
    "free.fr",
    "web.de",
    "gmx.de",
    "gmx.net",
    "zoho.com",
    "yandex.com",
    "mail.ru",
    "tutanota.com",
    "tuta.io",
    "hey.com",
    "fastmail.com",
    "outlook.co.uk",
    "blueyonder.co.uk",
    "virgin.net",
    "plus.net",
    "aol.co.uk",
    "terra.es",
    "ono.com",
    "jazztel.es",
    "vodafone.it",
    "vodafone.es",
];
const EMAIL_TLD_TYPOS = [
    "con" => "com",
    "cmo" => "com",
    "ocm" => "com",
    "vom" => "com",
    "xom" => "com",
    "comm" => "com",
    "coom" => "com",
    "cpm" => "com",
    "clm" => "com",
    "om" => "com",
    "iy" => "it",
    "ti" => "it",
    "itt" => "it",
    "ir" => "it",
    "ess" => "es",
    "se" => "es",
    "co.ik" => "co.uk",
    "co.uj" => "co.uk",
    "co.ul" => "co.uk",
    "co.ukk" => "co.uk",
    "couk" => "co.uk",
];
const EMAIL_DISPOSABLE = [
    "mailinator.com",
    "yopmail.com",
    "yopmail.fr",
    "yopmail.net",
    "guerrillamail.com",
    "guerrillamail.net",
    "guerrillamail.org",
    "guerrillamailblock.com",
    "sharklasers.com",
    "grr.la",
    "pokemail.net",
    "spam4.me",
    "10minutemail.com",
    "10minutemail.net",
    "10minutemail.co.uk",
    "20minutemail.com",
    "temp-mail.org",
    "temp-mail.io",
    "tempmail.com",
    "tempmail.net",
    "tempmailo.com",
    "tempail.com",
    "tempr.email",
    "emailondeck.com",
    "throwawaymail.com",
    "trashmail.com",
    "trashmail.de",
    "trashmail.net",
    "mytrashmail.com",
    "getnada.com",
    "nada.email",
    "maildrop.cc",
    "mailnesia.com",
    "mintemail.com",
    "mohmal.com",
    "dispostable.com",
    "fakeinbox.com",
    "fakemail.net",
    "mailcatch.com",
    "mailpoof.com",
    "moakt.com",
    "mvrht.com",
    "dropmail.me",
    "spambox.us",
    "spamgourmet.com",
    "jetable.org",
    "discard.email",
    "discardmail.com",
    "getairmail.com",
    "burnermail.io",
    "inboxkitten.com",
    "emailfake.com",
    "fake-mail.net",
    "tmail.ws",
    "tmpmail.org",
    "tmpmail.net",
    "luxusmail.org",
    "mail.tm",
    "mailtemp.net",
    "1secmail.com",
    "1secmail.org",
    "1secmail.net",
    "harakirimail.com",
    "mailforspam.com",
    "spamfree24.org",
    "trash-mail.com",
    "wegwerfmail.de",
    "einrot.com",
    "crazymailing.com",
    "mailsac.com",
    "tempinbox.com",
    "emailtemporanea.net",
    "emailtemporanea.com",
    "mailtemporanea.com",
    "correotemporal.org",
    "guerrillamail.info",
    "mailnull.com",
    "trbvm.com",
    "kurzepost.de",
    "byom.de",
    "tempmailaddress.com",
    "boun.cr",
    "mailinator.net",
    "mailinator2.com",
    "notmailinator.com",
    "tempmail.plus",
];
const EMAIL_FAKE_DOMAINS = [
    "test.com",
    "test.it",
    "test.es",
    "test.co.uk",
    "example.com",
    "example.org",
    "example.net",
    "example.it",
    "example.es",
    "domain.com",
    "dominio.it",
    "dominio.com",
    "prova.it",
    "prova.com",
    "fake.com",
    "nomail.com",
    "noemail.com",
    "nessuna.it",
    "asd.com",
    "asdf.com",
    "qwerty.com",
    "abc.com",
    "xxx.com",
    "aaa.com",
];
const EMAIL_MASH = '/^(?:asd|asdf|asdasd|qwe|qwerty|zxc|zxcv|aaa+|xxx+|abc|test|prova|fake|nome|name)\d*$/';

function email_distance(string $a, string $b): int
{
    $la = strlen($a); $lb = strlen($b); $d = [];
    for ($i = 0; $i <= $la; $i++) $d[$i][0] = $i;
    for ($j = 0; $j <= $lb; $j++) $d[0][$j] = $j;
    for ($i = 1; $i <= $la; $i++) {
        for ($j = 1; $j <= $lb; $j++) {
            $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
            $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $cost);
            if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
            }
        }
    }
    return $d[$la][$lb];
}

function email_suggest_domain(string $domain): string
{
    if (in_array($domain, EMAIL_KNOWN, true)) return '';
    $best = ''; $bestD = 99;
    foreach (EMAIL_KNOWN as $k) {
        $dd = email_distance($domain, $k);
        if ($dd < $bestD) { $bestD = $dd; $best = $k; }
    }
    if ($bestD === 1 && strlen($domain) >= 6) return $best;
    if ($bestD === 2 && strlen($domain) >= 9) return $best;
    foreach (EMAIL_TLD_TYPOS as $bad => $good) {
        if (str_ends_with($domain, '.' . $bad)) {
            $fixed = substr($domain, 0, -strlen((string) $bad)) . $good;
            if (in_array($fixed, EMAIL_KNOWN, true)) return $fixed;
            $again = email_suggest_domain($fixed);
            return $again !== '' ? $again : $fixed;
        }
    }
    return '';
}

function check_email(string $raw): array
{
    $fail = static fn(string $reason, string $suggestion = ''): array =>
        ['ok' => false, 'email' => '', 'reason' => $reason, 'suggestion' => $suggestion];

    $email = strtolower(trim($raw));
    $at = strrpos($email, '@');
    if (strlen($email) > 254 || $at === false || $at < 1 || strpos($email, '@') !== $at) return $fail('format');
    $local = substr($email, 0, $at);
    $domain = substr($email, $at + 1);
    if (strlen($local) > 64 || !preg_match("/^[a-z0-9._%+'-]+$/", $local) || preg_match('/^\.|\.$|\.\./', $local)) {
        return $fail('format');
    }
    $labels = explode('.', $domain);
    if (count($labels) < 2) return $fail('format');
    foreach ($labels as $label) {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $label)) return $fail('format');
    }
    if (!preg_match('/^[a-z]{2,24}$/', end($labels))) return $fail('format');

    if (in_array($domain, EMAIL_DISPOSABLE, true)) return $fail('disposable');
    if (in_array($domain, EMAIL_FAKE_DOMAINS, true) || (preg_match(EMAIL_MASH, $local) && preg_match(EMAIL_MASH, $labels[0]))) {
        return $fail('fake');
    }
    $fix = email_suggest_domain($domain);
    if ($fix !== '') return $fail('typo', $local . '@' . $fix);
    return ['ok' => true, 'email' => $email, 'reason' => '', 'suggestion' => ''];
}

/**
 * Can the domain receive mail (MX, or A as the RFC 5321 fallback)?
 * If DNS itself isn't answering (a known domain fails too), let it through.
 */
function email_domain_receives_mail(string $email): bool
{
    $domain = substr($email, (int) strrpos($email, '@') + 1);
    if (checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A')) return true;
    return !checkdnsrr('gmail.com', 'MX'); // DNS down → fail open
}
