<?php

use App\Models\Company;
use App\Services\EmailFinder;
use App\Services\WebsiteFinder;
use Illuminate\Support\Facades\Http;

describe('website finder', function () {
    it('guesses likely domain names', function () {
        $domains = app(WebsiteFinder::class)->candidateDomains('Dlm Soft ( Developp Logiciel Metier ) (Dlm Soft)');

        expect($domains)->toContain('dlmsoft.fr', 'dlm-soft.fr')->not->toContain('sarl.fr');
    });

    it('keeps a site only if it shows the SIREN or the address', function (string $page, bool $kept) {
        $company = Company::factory()->create(['siren' => '434009973', 'name' => 'Kiwo', 'postal_code' => '21200', 'city' => 'Beaune']);
        Http::fake(['kiwo.fr' => Http::response(str_repeat($page, 10)), '*' => Http::response('', 404)]);

        expect((bool) app(WebsiteFinder::class)->verify('https://kiwo.fr', $company))->toBe($kept);
    })->with([
        'SIREN shown' => ['<p>Kiwo SAS, 8 rue Gambut 21200 BEAUNE, SIREN 434 009 973. Logiciels pour EHPAD.</p>', true],
        'address shown' => ['<p>Kiwo, 8 rue Gambut, 21200 Beaune. Logiciels pour les EHPAD et SSIAD.</p>', true],
        'namesake' => ['<p>Kiwo Inc, screen printing supplies, Seattle WA, since 1980.</p>', false],
    ]);

    it('rejects a parked domain', function () {
        $company = Company::factory()->create(['name' => 'Kiwo']);
        Http::fake(['*' => Http::response('<p>kiwo.fr - This domain is for sale on HugeDomains</p>')]);

        expect(app(WebsiteFinder::class)->verify('https://kiwo.fr', $company))->toBeNull();
    });
});

describe('email finder', function () {
    it('rejects form placeholders, DPO and no-reply addresses', function (string $email) {
        expect(app(EmailFinder::class)->isFake($email))->toBeTrue();
    })->with(['nom.prenom@monemail.com', 'email@domaine.com', 'adresse@mail.com', 'dpo@kiwo.fr', 'rgpd@c2ip.com', 'noreply@site.fr', 'logo@2x.png']);

    it('recognises the company domain', function () {
        $finder = app(EmailFinder::class);

        expect($finder->relatedDomain('bee2link.fr', 'bee2linkgroup.com', 'Bee2Link'))->toBeTrue()
            ->and($finder->relatedDomain('vertech.fr', 'vertech.eu', 'Vertech'))->toBeTrue()
            ->and($finder->relatedDomain('sadeveloppement.com', 'haby-solution.com', 'Sa Developpement'))->toBeTrue()
            ->and($finder->relatedDomain('derriennic.com', 'bee2linkgroup.com', 'Bee2Link'))->toBeFalse();
    });

    it('rejects the web host address', function () {
        expect(app(EmailFinder::class)->score('support@ovh.com', 'Ce site est hébergé par OVH, contact :', 'kiwo.fr', 'Kiwo'))->toBeNull();
    });

    it('prefers recruitment, then contact, then sales addresses', function () {
        $finder = app(EmailFinder::class);
        $score = fn ($email) => $finder->score($email, '', 'x.fr', 'X')['points'];

        expect($score('recrutement@x.fr'))->toBeGreaterThan($score('contact@x.fr'))
            ->and($score('contact@x.fr'))->toBeGreaterThan($score('commercial@x.fr'));
    });

    it('finds real phone numbers but not SIREN fragments', function () {
        $phones = app(EmailFinder::class)->phonesIn('<p>SIREN 530930015000</p><p>Tél : 03 85 46 01 50</p><a href="tel:+33385981919">appeler</a><p>0930015000</p>');

        expect($phones)->toContain('03 85 46 01 50', '03 85 98 19 19')->not->toContain('09 30 01 50 00');
    });

    it('decodes Cloudflare-protected and [at] emails', function () {
        $key = 0x42;
        $code = sprintf('%02x', $key).implode('', array_map(fn ($c) => sprintf('%02x', ord($c) ^ $key), str_split('contact@x.fr')));

        $found = app(EmailFinder::class)->emailsIn('<a data-cfemail="'.$code.'">[email protected]</a> info [at] y.fr');

        expect(array_keys($found))->toContain('contact@x.fr', 'info@y.fr');
    });

    it('picks the official address shown on several pages over a staff directory', function () {
        $team = implode(' ', array_map(fn ($n) => "<span>$n@acme.fr</span>", ['anne.martin', 'paul.durand', 'marc.petit', 'lea.bernard', 'jean.roux', 'eva.moreau']));
        Http::fake([
            'acme.fr/qui-sommes-nous' => Http::response("<p>Notre équipe</p>$team"),
            'acme.fr/contact*' => Http::response('<p>Écrivez-nous : commerce@acme.fr</p>'),
            'acme.fr/mentions-legales*' => Http::response('<p>Contact : commerce@acme.fr</p>'),
            'acme.fr' => Http::response('<p>Acme</p><a href="/qui-sommes-nous">équipe</a><a href="/contact">contact</a><footer>commerce@acme.fr</footer>'),
            '*' => Http::response('', 404),
        ]);

        expect(app(EmailFinder::class)->find('https://acme.fr', 'Acme')['email'])->toBe('commerce@acme.fr');
    });
});
