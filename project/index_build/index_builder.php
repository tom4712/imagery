<?php
// /project/index_build/index_builder.php
if (!defined('_GNUBOARD_')) exit;

class DxfIndexGenerator {
    private $g50_sheets = []; // 파싱된 5만 도엽 구조체

    public function __construct($base_dxf_path) {
        $this->parseBaseDxf($base_dxf_path);
    }

    /**
     * 전국 5만 도엽 기본 템플릿 파싱 (LWPOLYLINE, TEXT)
     */
    private function parseBaseDxf($path) {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total = count($lines);

        $cur_entity = '';
        $cur_layer = '';
        $cur_text = '';
        $poly_pts = [];
        $x10 = null; $y20 = null;

        for ($i = 0; $i < $total; $i += 2) {
            $code = trim($lines[$i]);
            $val = isset($lines[$i+1]) ? trim($lines[$i+1]) : '';

            if ($code === '0') {
                $this->flushBaseEntity($cur_entity, $cur_layer, $cur_text, $poly_pts, $x10, $y20);
                $cur_entity = $val;
                $cur_layer = '';
                $cur_text = '';
                $poly_pts = [];
                $x10 = null; $y20 = null;
                continue;
            }

            if ($code === '8') $cur_layer = $val;
            if ($code === '1') $cur_text = mb_convert_encoding($val, 'UTF-8', 'CP949');
            if ($code === '10') $x10 = (float)$val;
            if ($code === '20') {
                $y20 = (float)$val;
                if ($cur_entity === 'LWPOLYLINE') {
                    $poly_pts[] = ['x' => $x10, 'y' => $y20];
                }
            }
        }
        $this->flushBaseEntity($cur_entity, $cur_layer, $cur_text, $poly_pts, $x10, $y20);
    }

    private function flushBaseEntity($type, $layer, $text, $pts, $x, $y) {
        if ($type === 'LWPOLYLINE' && $layer === 'G50') {
            // 바운딩 박스 계산 및 도엽 ID 할당
            $minX = min(array_column($pts, 'x'));
            $maxX = max(array_column($pts, 'x'));
            $minY = min(array_column($pts, 'y'));
            $maxY = max(array_column($pts, 'y'));
            $this->g50_sheets[] = [
                'type' => 'POLY',
                'layer' => 'G50',
                'bbox' => [$minX, $minY, $maxX, $maxY],
                'points' => $pts
            ];
        } else if ($type === 'TEXT' && in_array($layer, ['N50000', 'T50000'])) {
            $this->g50_sheets[] = [
                'type' => 'TEXT',
                'layer' => $layer,
                'x' => $x,
                'y' => $y,
                'text' => $text
            ];
        }
    }

    /**
     * 선택된 도엽 리스트와 EO 주점을 병합하여 최종 DXF 스트림 생성
     */
    public function generateMergedDxf($selected_sheet_names, $eo_points) {
        $out = [];
        // DXF HEADER / TABLES
        $out[] = "0\nSECTION\n2\nHEADER\n9\n\$ACADVER\n1\nAC1015\n9\n\$DWGCODEPAGE\n3\nANSI_949\n0\nENDSEC";
        $out[] = "0\nSECTION\n2\nTABLES\n0\nTABLE\n2\nLAYER\n70\n6";
        $out[] = "0\nLAYER\n2\n0\n70\n0\n62\n7\n6\nCONTINUOUS";
        $out[] = "0\nLAYER\n2\nG50\n70\n0\n62\n7\n6\nCONTINUOUS";
        $out[] = "0\nLAYER\n2\nN50000\n70\n0\n62\n7\n6\nCONTINUOUS";
        $out[] = "0\nLAYER\n2\nT50000\n70\n0\n62\n250\n6\nCONTINUOUS";
        $out[] = "0\nLAYER\n2\n0826_PP\n70\n0\n62\n1\n6\nCONTINUOUS";
        $out[] = "0\nLAYER\n2\n0826_TT\n70\n0\n62\n7\n6\nCONTINUOUS";
        $out[] = "0\nENDTAB\n0\nENDSEC";
        
        // ENTITIES SECTION
        $out[] = "0\nSECTION\n2\nENTITIES";

        // 1. 선택된 5만 도엽 (G50, N50000, T50000) 출력
        // (실제 구현 시 $selected_sheet_names 범위 내에 포함되는 엔티티만 필터링)
        foreach ($this->g50_sheets as $item) {
            if ($item['type'] === 'POLY') {
                $out[] = "0\nLWPOLYLINE\n8\n{$item['layer']}\n90\n" . count($item['points']) . "\n70\n1\n43\n0.0";
                foreach ($item['points'] as $p) {
                    $out[] = "10\n{$p['x']}\n20\n{$p['y']}";
                }
            } else if ($item['type'] === 'TEXT') {
                $raw_text = mb_convert_encoding($item['text'], 'CP949', 'UTF-8');
                $out[] = "0\nTEXT\n8\n{$item['layer']}\n10\n{$item['x']}\n20\n{$item['y']}\n40\n2500.0\n1\n{$raw_text}";
            }
        }

        // 2. 촬영 주점 및 텍스트 (EO 데이터) 출력
        foreach ($eo_points as $pt) {
            $layer_pp = $pt['is_reshoot'] ? '0826_PP_A' : '0826_PP';
            $layer_tt = $pt['is_reshoot'] ? '0826_TT_A' : '0826_TT';
            
            // CIRCLE (주점)
            $out[] = "0\nCIRCLE\n8\n{$layer_pp}\n10\n{$pt['x']}\n20\n{$pt['y']}\n40\n50.0";
            
            // TEXT (사진번호)
            $out[] = "0\nTEXT\n8\n{$layer_tt}\n10\n{$pt['x']}\n20\n{$pt['y']}\n40\n150.0\n1\n{$pt['photo_no']}\n50\n315.0";
        }

        $out[] = "0\nENDSEC\n0\nEOF";
        return implode("\n", $out);
    }
}