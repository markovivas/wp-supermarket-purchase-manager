<?php
class WPSGL_Utils {
    
    /**
     * Valida se uma data é válida
     */
    public static function validate_date($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Formata valor monetário
     */
    public static function format_currency($value) {
        return 'R$ ' . number_format(floatval($value), 2, ',', '.');
    }
    
    /**
     * Formata número decimal
     */
    public static function format_number($value, $decimals = 3) {
        return number_format(floatval($value), $decimals, ',', '.');
    }
    
    /**
     * Gera um código de barras único
     */
    public static function generate_barcode() {
        return '789' . mt_rand(1000000000, 9999999999);
    }
    
    /**
     * Sanitiza array multidimensional
     */
    public static function sanitize_array($array) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::sanitize_array($value);
            } else {
                $array[$key] = sanitize_text_field($value);
            }
        }
        return $array;
    }
    
    /**
     * Converte unidades
     */
    public static function convert_unit($value, $from, $to) {
        $conversions = array(
            'kg' => array(
                'g' => 1000,
                'kg' => 1
            ),
            'g' => array(
                'kg' => 0.001,
                'g' => 1
            ),
            'l' => array(
                'ml' => 1000,
                'l' => 1
            ),
            'ml' => array(
                'l' => 0.001,
                'ml' => 1
            )
        );
        
        if (!isset($conversions[$from][$to])) {
            return $value;
        }
        
        return $value * $conversions[$from][$to];
    }
    
    /**
     * Obtém o nome do mês em português
     */
    public static function get_month_name($month_number) {
        $months = array(
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro'
        );
        
        return isset($months[$month_number]) ? $months[$month_number] : '';
    }
    
    /**
     * Gera cores para gráficos
     */
    public static function generate_chart_colors($count) {
        $colors = array();
        $base_colors = array(
            '#4CAF50', '#2196F3', '#FF9800', '#E91E63',
            '#9C27B0', '#3F51B5', '#00BCD4', '#8BC34A',
            '#FF5722', '#795548', '#607D8B', '#009688'
        );
        
        for ($i = 0; $i < $count; $i++) {
            if ($i < count($base_colors)) {
                $colors[] = $base_colors[$i];
            } else {
                // Gera cores aleatórias se precisar mais
                $colors[] = '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
            }
        }
        
        return $colors;
    }
    
    /**
     * Valida email
     */
    public static function validate_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Limita texto
     */
    public static function limit_text($text, $limit = 100) {
        if (strlen($text) <= $limit) {
            return $text;
        }
        
        $text = substr($text, 0, $limit);
        $text = substr($text, 0, strrpos($text, ' '));
        return $text . '...';
    }
    
    /**
     * Calcula média ponderada
     */
    public static function weighted_average($values, $weights) {
        if (count($values) !== count($weights) || empty($values)) {
            return 0;
        }
        
        $sum = 0;
        $weight_sum = 0;
        
        for ($i = 0; $i < count($values); $i++) {
            $sum += $values[$i] * $weights[$i];
            $weight_sum += $weights[$i];
        }
        
        return $weight_sum > 0 ? $sum / $weight_sum : 0;
    }
    
    /**
     * Obtém dias da semana em português
     */
    public static function get_weekday_names() {
        return array(
            'Domingo',
            'Segunda-feira',
            'Terça-feira',
            'Quarta-feira',
            'Quinta-feira',
            'Sexta-feira',
            'Sábado'
        );
    }
    
    /**
     * Gera CSV a partir de array
     */
    public static function array_to_csv($data, $headers = null) {
        $output = fopen('php://temp', 'w');
        
        if ($headers) {
            fputcsv($output, $headers, ';');
        }
        
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Log de erros do plugin
     */
    public static function log_error($message, $data = null) {
        if (WP_DEBUG === true) {
            $log_message = '[' . date('Y-m-d H:i:s') . '] WP Supermarket Manager: ' . $message;
            
            if ($data) {
                $log_message .= ' Data: ' . print_r($data, true);
            }
            
            error_log($log_message);
        }
    }
} 
