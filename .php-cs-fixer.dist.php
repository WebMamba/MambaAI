<?php

declare(strict_types=1);

/*
 * Configuration alignée sur celle du repo symfony/symfony :
 * - rule sets `@Symfony` + `@Symfony:risky`
 * - rule sets `@PHP84Migration:risky` pour la modernisation syntaxique
 * - pas de header_comment (pas de copyright dans ce projet)
 */

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
    ->append([__FILE__])
    ->notPath('#/Fixtures/#')
    ->notPath('Resources/agent-templates');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP8x4Migration:risky' => true,
        // Le projet n'a pas de header de copyright — on désactive explicitement.
        'header_comment' => false,
        // Les noms de tests en snake_case sont l'idiome PHPUnit moderne — on garde.
        'php_unit_method_casing' => false,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/var/.php-cs-fixer.cache');
