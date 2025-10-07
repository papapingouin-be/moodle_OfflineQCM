<?php
declare(strict_types=1);

/**
 * Classe de liaison avec le module de grilles de réponses.
 * Cette implémentation est un simple socle en attendant
 * l'intégration du projet "grille".
 */
final class GridModule {
    private string $moduleDir;

    public function __construct(string $moduleDir = GRID_MODULE_PATH) {
        $this->moduleDir = $moduleDir;
    }

    /**
     * Génère les grilles de réponses ainsi que la liste des participants.
     *
     * @param string $zipRoot   Dossier contenant les fichiers extraits du ZIP.
     * @param string $outputDir Dossier où sauvegarder les PDF générés.
     * @return array{grids:string,participants:string} Chemins des fichiers générés.
     */
    public function generate(string $zipRoot, string $outputDir): array {
        // Le projet grille n'est pas encore intégré : on signale l'absence.
        throw new RuntimeException('Module grille non installé dans ' . $this->moduleDir);
    }
}
