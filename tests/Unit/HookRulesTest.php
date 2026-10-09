<?php

use App\Services\Hooks\HookRules;

$good = 'Vos logiciels de gestion pour les EHPAD m\'intéressent, car j\'aimerais travailler sur des applications utilisées au quotidien par des équipes de terrain.';

it('accepts an honest one-sentence hook', function () use ($good) {
    expect((new HookRules)->problems($good, 'Kiwo'))->toBe([]);
});

it('refuses hooks that break the honesty or form rules', function (string $hook, string $why) {
    expect((new HookRules)->problems($hook, 'X'))->not->toBe([], $why);
})->with([
    ['Votre logiciel est le meilleur du marché et j\'aimerais beaucoup y travailler cette année.', 'flatterie'],
    ['Vos logiciels m\'intéressent car j\'ai développé seul une application Laravel complète.', 'seul / développé'],
    ['Vos outils m\'intéressent car je maîtrise Laravel et PHP depuis plusieurs années déjà.', 'je maîtrise'],
    ['Vos logiciels m\'intéressent car ce projet correspond à mon expertise en développement backend.', 'mon expertise'],
    ['Vos logiciels m\'intéressent car j\'ai contribué à un projet en concevant une application Android pour les employés.', 'en concevant'],
    ['Vos logiciels m\'intéressent — j\'ai contribué à une migration Laravel pendant mon stage.', 'tiret cadratin'],
    ['Vos logiciels m\'intéressent. J\'ai contribué à une migration Laravel pendant mon stage.', 'deux phrases'],
    ['Vos logiciels m\'intéressent énormément, car c\'est exactement ce que je cherche !', 'exclamation'],
    ['Trop court.', 'longueur'],
    ['Le développement web est un domaine qui m\'intéresse beaucoup depuis le lycée et le BTS.', 'ne parle pas de l\'entreprise'],
]);

it('keeps only significant words to compare a hook with its proof', function () {
    $words = (new HookRules)->significantWords('Vos logiciels pour les EHPAD et la facturation');

    expect($words)->toContain('ehpad', 'facturation')->not->toContain('logiciels');
});
