#!/usr/bin/env php
<?php
/**
 * FormFlow Pro Translation Manager
 *
 * Sistema de tradução em etapas para o arquivo .po
 *
 * Comandos:
 *   php translation-manager.php status          - Mostra status atual
 *   php translation-manager.php extract [N]     - Extrai próximas N strings (default: 100)
 *   php translation-manager.php apply           - Aplica traduções do arquivo batch
 *   php translation-manager.php compile         - Compila .po para .mo
 *
 * @package FormFlowPro
 */

define('LANGUAGES_DIR', dirname(__DIR__) . '/languages');
define('PO_FILE', LANGUAGES_DIR . '/formflow-pro-pt_BR.po');
define('POT_FILE', LANGUAGES_DIR . '/formflow-pro.pot');
define('BATCH_FILE', LANGUAGES_DIR . '/translation-batch.txt');
define('PROGRESS_FILE', LANGUAGES_DIR . '/.translation-progress.json');

class TranslationManager
{
    private array $progress;

    public function __construct()
    {
        $this->loadProgress();
    }

    /**
     * Carrega progresso salvo
     */
    private function loadProgress(): void
    {
        if (file_exists(PROGRESS_FILE)) {
            $this->progress = json_decode(file_get_contents(PROGRESS_FILE), true) ?? [];
        } else {
            $this->progress = [
                'last_batch' => 0,
                'total_translated' => 0,
                'batches_completed' => 0,
                'started_at' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Salva progresso
     */
    private function saveProgress(): void
    {
        $this->progress['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents(PROGRESS_FILE, json_encode($this->progress, JSON_PRETTY_PRINT));
    }

    /**
     * Mostra status atual da tradução
     */
    public function status(): void
    {
        $stats = $this->getStats();

        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║         📊 STATUS DA TRADUÇÃO - FormFlow Pro                 ║\n";
        echo "╠══════════════════════════════════════════════════════════════╣\n";
        printf("║  Total de strings:        %6d                             ║\n", $stats['total']);
        printf("║  Traduzidas:              %6d  (%5.1f%%)                   ║\n", $stats['translated'], $stats['percent']);
        printf("║  Não traduzidas:          %6d  (%5.1f%%)                   ║\n", $stats['untranslated'], 100 - $stats['percent']);
        echo "╠══════════════════════════════════════════════════════════════╣\n";
        printf("║  Batches completados:     %6d                             ║\n", $this->progress['batches_completed'] ?? 0);
        printf("║  Último batch:            %6d strings                     ║\n", $this->progress['last_batch'] ?? 0);
        echo "╠══════════════════════════════════════════════════════════════╣\n";

        // Barra de progresso
        $barWidth = 50;
        $filled = (int) round($stats['percent'] / 100 * $barWidth);
        $bar = str_repeat('█', $filled) . str_repeat('░', $barWidth - $filled);
        echo "║  [$bar]  ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n";

        // Estimativa de etapas restantes
        $remaining = $stats['untranslated'];
        $batchSize = 100;
        $batchesRemaining = ceil($remaining / $batchSize);

        echo "\n📋 Próximos passos:\n";
        echo "   1. Execute: php translation-manager.php extract 100\n";
        echo "   2. Traduza o arquivo: languages/translation-batch.txt\n";
        echo "   3. Execute: php translation-manager.php apply\n";
        echo "   4. Repita até 100%\n";
        echo "\n⏱️  Estimativa: ~{$batchesRemaining} etapas restantes (100 strings/etapa)\n\n";
    }

    /**
     * Obtém estatísticas do arquivo .po
     */
    private function getStats(): array
    {
        $content = file_get_contents(PO_FILE);

        // Conta total de msgid (excluindo o header vazio)
        preg_match_all('/^msgid "(.+)"$/m', $content, $msgids);
        $total = count($msgids[1]);

        // Conta msgstr vazios (não traduzidos)
        preg_match_all('/^msgid "(.+)"\nmsgstr ""$/m', $content, $untranslated);
        $untranslatedCount = count($untranslated[1]);

        $translated = $total - $untranslatedCount;
        $percent = $total > 0 ? ($translated / $total) * 100 : 0;

        return [
            'total' => $total,
            'translated' => $translated,
            'untranslated' => $untranslatedCount,
            'percent' => $percent,
        ];
    }

    /**
     * Extrai próximas N strings não traduzidas
     */
    public function extract(int $count = 100): void
    {
        $content = file_get_contents(PO_FILE);

        // Encontra strings não traduzidas
        preg_match_all('/^msgid "(.+)"\nmsgstr ""$/m', $content, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            echo "✅ Todas as strings já estão traduzidas!\n";
            return;
        }

        $batch = array_slice($matches, 0, $count);

        // Cria arquivo de batch para tradução
        $output = "# FormFlow Pro - Batch de Tradução\n";
        $output .= "# Strings: " . count($batch) . "\n";
        $output .= "# Data: " . date('Y-m-d H:i:s') . "\n";
        $output .= "#\n";
        $output .= "# INSTRUÇÕES:\n";
        $output .= "# 1. Traduza cada linha MSGSTR (após o =)\n";
        $output .= "# 2. Mantenha os placeholders (%s, %d, %1\$s, etc.) intactos\n";
        $output .= "# 3. Não modifique as linhas MSGID\n";
        $output .= "# 4. Após traduzir, execute: php translation-manager.php apply\n";
        $output .= "#\n";
        $output .= "# ════════════════════════════════════════════════════════════\n\n";

        foreach ($batch as $i => $match) {
            $num = $i + 1;
            $output .= "# [{$num}/" . count($batch) . "]\n";
            $output .= "MSGID={$match[1]}\n";
            $output .= "MSGSTR=\n\n";
        }

        file_put_contents(BATCH_FILE, $output);

        $this->progress['last_batch'] = count($batch);
        $this->saveProgress();

        echo "\n✅ Batch criado com " . count($batch) . " strings!\n";
        echo "📄 Arquivo: languages/translation-batch.txt\n";
        echo "\n📝 Próximo passo: Traduza o arquivo e execute 'php translation-manager.php apply'\n\n";
    }

    /**
     * Aplica traduções do arquivo batch
     */
    public function apply(): void
    {
        if (!file_exists(BATCH_FILE)) {
            echo "❌ Arquivo de batch não encontrado!\n";
            echo "   Execute primeiro: php translation-manager.php extract\n";
            return;
        }

        $batchContent = file_get_contents(BATCH_FILE);
        $poContent = file_get_contents(PO_FILE);

        // Parse do batch
        preg_match_all('/MSGID=(.+)\nMSGSTR=(.+)?/m', $batchContent, $matches, PREG_SET_ORDER);

        $applied = 0;
        $skipped = 0;

        foreach ($matches as $match) {
            $msgid = trim($match[1]);
            $msgstr = isset($match[2]) ? trim($match[2]) : '';

            if (empty($msgstr)) {
                $skipped++;
                continue;
            }

            // Escapa caracteres especiais para regex
            $msgidEscaped = preg_quote($msgid, '/');

            // Substitui no arquivo .po
            $pattern = '/^(msgid "' . $msgidEscaped . '")\nmsgstr ""$/m';
            $replacement = "$1\nmsgstr \"" . addslashes($msgstr) . "\"";

            $newContent = preg_replace($pattern, $replacement, $poContent, 1, $count);

            if ($count > 0) {
                $poContent = $newContent;
                $applied++;
            }
        }

        if ($applied > 0) {
            // Atualiza data de revisão
            $poContent = preg_replace(
                '/PO-Revision-Date: .+/',
                'PO-Revision-Date: ' . date('Y-m-d H:i+0000'),
                $poContent
            );

            file_put_contents(PO_FILE, $poContent);

            $this->progress['batches_completed'] = ($this->progress['batches_completed'] ?? 0) + 1;
            $this->progress['total_translated'] = ($this->progress['total_translated'] ?? 0) + $applied;
            $this->saveProgress();
        }

        // Remove arquivo de batch processado
        unlink(BATCH_FILE);

        echo "\n✅ Traduções aplicadas!\n";
        echo "   Aplicadas: {$applied}\n";
        echo "   Ignoradas (vazias): {$skipped}\n";
        echo "\n📊 Execute 'php translation-manager.php status' para ver o progresso\n";
        echo "📦 Execute 'php translation-manager.php compile' para gerar o .mo\n\n";
    }

    /**
     * Compila .po para .mo
     */
    public function compile(): void
    {
        $moFile = str_replace('.po', '.mo', PO_FILE);

        // Tenta usar msgfmt se disponível
        $output = [];
        $returnCode = 0;
        exec('which msgfmt 2>/dev/null', $output, $returnCode);

        if ($returnCode === 0) {
            exec("msgfmt -o " . escapeshellarg($moFile) . " " . escapeshellarg(PO_FILE), $output, $returnCode);

            if ($returnCode === 0) {
                echo "✅ Arquivo .mo compilado com sucesso!\n";
                echo "📄 " . basename($moFile) . "\n";
                return;
            }
        }

        // Fallback: compilação PHP simples
        echo "⚠️  msgfmt não disponível, usando compilador PHP...\n";
        $this->compilePHP();
    }

    /**
     * Compilador .mo em PHP puro
     */
    private function compilePHP(): void
    {
        $content = file_get_contents(PO_FILE);
        $moFile = str_replace('.po', '.mo', PO_FILE);

        // Parse simples do .po
        preg_match_all('/msgid "(.*)"\nmsgstr "(.+)"/m', $content, $matches, PREG_SET_ORDER);

        $translations = [];
        foreach ($matches as $match) {
            if (!empty($match[1]) && !empty($match[2])) {
                $translations[$match[1]] = $match[2];
            }
        }

        // Gera arquivo .mo (formato simplificado)
        $mo = $this->generateMO($translations);
        file_put_contents($moFile, $mo);

        echo "✅ Arquivo .mo compilado (PHP fallback)!\n";
        echo "📄 " . basename($moFile) . "\n";
        echo "📊 " . count($translations) . " traduções incluídas\n";
    }

    /**
     * Gera conteúdo do arquivo .mo
     */
    private function generateMO(array $translations): string
    {
        ksort($translations);
        $count = count($translations);

        // Header do .mo
        $mo = pack('L', 0x950412de); // Magic number
        $mo .= pack('L', 0);         // Revision
        $mo .= pack('L', $count);    // Number of strings
        $mo .= pack('L', 28);        // Offset of original strings
        $mo .= pack('L', 28 + $count * 8); // Offset of translations
        $mo .= pack('L', 0);         // Size of hash table
        $mo .= pack('L', 0);         // Offset of hash table

        $originals = '';
        $translationsStr = '';
        $origTable = [];
        $transTable = [];

        $offset = 28 + $count * 16;

        foreach ($translations as $original => $translation) {
            $origTable[] = [strlen($original), $offset];
            $originals .= $original . "\0";
            $offset += strlen($original) + 1;
        }

        foreach ($translations as $original => $translation) {
            $transTable[] = [strlen($translation), $offset];
            $translationsStr .= $translation . "\0";
            $offset += strlen($translation) + 1;
        }

        foreach ($origTable as $entry) {
            $mo .= pack('L', $entry[0]);
            $mo .= pack('L', $entry[1]);
        }

        foreach ($transTable as $entry) {
            $mo .= pack('L', $entry[0]);
            $mo .= pack('L', $entry[1]);
        }

        $mo .= $originals;
        $mo .= $translationsStr;

        return $mo;
    }
}

// CLI Handler
if (php_sapi_name() === 'cli') {
    $manager = new TranslationManager();
    $command = $argv[1] ?? 'status';

    switch ($command) {
        case 'status':
            $manager->status();
            break;

        case 'extract':
            $count = (int) ($argv[2] ?? 100);
            $manager->extract($count);
            break;

        case 'apply':
            $manager->apply();
            break;

        case 'compile':
            $manager->compile();
            break;

        default:
            echo "Uso: php translation-manager.php [comando]\n\n";
            echo "Comandos:\n";
            echo "  status          Mostra status atual da tradução\n";
            echo "  extract [N]     Extrai próximas N strings (default: 100)\n";
            echo "  apply           Aplica traduções do arquivo batch\n";
            echo "  compile         Compila .po para .mo\n";
            break;
    }
}
