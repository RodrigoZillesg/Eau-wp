<?php
namespace EauSystem;

/**
 * Classe para manipular arquivos CSV
 */
class Eau_CSV_Handler {

    private $upload_dir;
    private $max_file_size = 10485760; // 10MB

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->upload_dir = $upload_dir['basedir'] . '/eau-system-csv/';

        // Cria diretório se não existir
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }
    }

    /**
     * Processa o upload do arquivo CSV
     */
    public function process_upload($file) {
        // Validações
        $validation = $this->validate_file($file);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Move arquivo para diretório de uploads
        $filename = $this->generate_unique_filename($file['name']);
        $filepath = $this->upload_dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return new \WP_Error('upload_failed', 'Falha ao mover arquivo.');
        }

        // Lê e interpreta o CSV
        $csv_data = $this->read_csv($filepath);

        if (is_wp_error($csv_data)) {
            unlink($filepath); // Remove arquivo se houver erro
            return $csv_data;
        }

        return array(
            'filename' => $filename,
            'filepath' => $filepath,
            'columns' => $csv_data['columns'],
            'row_count' => $csv_data['row_count'],
            'sample_data' => $csv_data['sample_data']
        );
    }

    /**
     * Valida o arquivo enviado
     */
    private function validate_file($file) {
        // Verifica se há erro no upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_error', 'Erro no upload do arquivo.');
        }

        // Verifica tamanho do arquivo
        if ($file['size'] > $this->max_file_size) {
            return new \WP_Error('file_too_large', 'Arquivo muito grande. Máximo: 10MB.');
        }

        // Verifica extensão
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'csv') {
            return new \WP_Error('invalid_file_type', 'Apenas arquivos CSV são permitidos.');
        }

        // Verifica MIME type
        $allowed_mimes = array('text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_mimes)) {
            return new \WP_Error('invalid_mime_type', 'Tipo de arquivo inválido.');
        }

        return true;
    }

    /**
     * Gera nome único para o arquivo
     */
    private function generate_unique_filename($original_name) {
        $ext = pathinfo($original_name, PATHINFO_EXTENSION);
        $name = pathinfo($original_name, PATHINFO_FILENAME);
        $name = sanitize_file_name($name);

        return $name . '_' . time() . '.' . $ext;
    }

    /**
     * Lê e interpreta o CSV
     */
    private function read_csv($filepath) {
        if (!file_exists($filepath)) {
            return new \WP_Error('file_not_found', 'Arquivo não encontrado.');
        }

        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            return new \WP_Error('cannot_read_file', 'Não foi possível ler o arquivo.');
        }

        // Detecta delimitador
        $first_line = fgets($handle);
        rewind($handle);
        $delimiter = $this->detect_delimiter($first_line);

        // Lê cabeçalhos (primeira linha)
        $columns = fgetcsv($handle, 0, $delimiter);

        if ($columns === false || empty($columns)) {
            fclose($handle);
            return new \WP_Error('invalid_csv', 'CSV inválido ou vazio.');
        }

        // Sanitiza nomes das colunas
        $columns = array_map(function($col) {
            return trim($col);
        }, $columns);

        // Lê algumas linhas de exemplo (até 5)
        $sample_data = array();
        $row_count = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row_count++;

            if (count($sample_data) < 5) {
                $sample_row = array();
                foreach ($columns as $index => $column) {
                    $sample_row[$column] = isset($row[$index]) ? $row[$index] : '';
                }
                $sample_data[] = $sample_row;
            }
        }

        fclose($handle);

        return array(
            'columns' => $columns,
            'row_count' => $row_count,
            'sample_data' => $sample_data,
            'delimiter' => $delimiter
        );
    }

    /**
     * Detecta o delimitador do CSV
     */
    private function detect_delimiter($line) {
        $delimiters = array(',', ';', "\t", '|');
        $delimiter_counts = array();

        foreach ($delimiters as $delimiter) {
            $delimiter_counts[$delimiter] = substr_count($line, $delimiter);
        }

        arsort($delimiter_counts);
        return key($delimiter_counts);
    }

    /**
     * Obtém dados completos do CSV para importação
     */
    public function get_csv_data($filepath, $selected_columns = null) {
        if (!file_exists($filepath)) {
            return new \WP_Error('file_not_found', 'Arquivo não encontrado.');
        }

        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            return new \WP_Error('cannot_read_file', 'Não foi possível ler o arquivo.');
        }

        $first_line = fgets($handle);
        rewind($handle);
        $delimiter = $this->detect_delimiter($first_line);

        $columns = fgetcsv($handle, 0, $delimiter);
        $data = array();

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row_data = array();

            foreach ($columns as $index => $column) {
                // Se colunas específicas foram selecionadas, filtra
                if ($selected_columns === null || in_array($column, $selected_columns)) {
                    $row_data[$column] = isset($row[$index]) ? $row[$index] : '';
                }
            }

            $data[] = $row_data;
        }

        fclose($handle);

        return $data;
    }
}
