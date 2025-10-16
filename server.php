<?php
require_once 'vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

// Dados de conexão - SQL Server
$serverName = "144.126.141.220"; // SQL Server
$connectionOptions = array(
    "Database" => "ssojob",
    "Uid" => "longato",
    "PWD" => "ti1009@",
    "CharacterSet" => "UTF-8"
);

// Endpoint para buscar lista de colaboradores
if (isset($_GET['colaboradores'])) {
    try {
        error_log("Tentando conectar ao banco de dados...");
        $conn = sqlsrv_connect($serverName, $connectionOptions);
        
        if ($conn === false) {
            $errors = sqlsrv_errors();
            error_log("Erro de conexão: " . print_r($errors, true));
            throw new Exception("Erro de conexão: " . print_r($errors, true));
        }
        
        error_log("Conexão estabelecida com sucesso");
        
        // Query para buscar colaboradores distintos
        $sql = "SELECT DISTINCT
                    f.CHAPA as cp_matricula,
                    f.NOME as NOMECOLABORADOR
                FROM [CorporeRM_JOB].[dbo].[PFUNC] as f
                ORDER BY f.NOME";
        
        error_log("Executando query: " . $sql);
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            error_log("Erro na query: " . print_r($errors, true));
            throw new Exception("Erro na query: " . print_r($errors, true));
        }
        
        error_log("Query executada com sucesso");
        
        $colaboradores = array();
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $colaboradores[] = $row;
        }
        
        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
        
        echo json_encode($colaboradores, JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Endpoint para buscar dados completos de um colaborador
if (isset($_GET['dadosColaborador']) && isset($_GET['chapa'])) {
    try {
        $conn = sqlsrv_connect($serverName, $connectionOptions);
        
        if ($conn === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }
        
        $chapa = $_GET['chapa'];
        
        // Query principal baseada na query fornecida
        $sql = "SELECT DISTINCT
                    [hty_id],
                    NOMECOLABORADOR,
                    cp_matricula,
                    FUNCAO,
                    tsk_lastexecutiondatehour,
                    cp_assinatura, 
                    cp_data, 
                    f.CODCOLIGADA as 'coligada',
                    ite_description,
                    cp_quantidade,
                    cp_ca,
                    entreguepor,
                    preco
                FROM
                    (
                        SELECT
                            f.[hty_id],
                            f.[htv_executiongroup],
                            f.[tsk_id],
                            t.[tss_id],
                            it.[ite_id],
                            t.[tsk_lastexecutiondatehour],
                            it.[ite_description],
                            f.[cp_quantidade],
                            f.[cp_ca],
                            f.[cp_assinatura],
                            [cp_colaborador] AS NOMECOLABORADOR,           
                            [cp_data],
                            [cp_matricula],
                            RANK() OVER (
                                PARTITION BY [cp_matricula],
                                f.ite_id
                                ORDER BY
                                   f.[tsk_lastexecutiondatehour] DESC
                            ) AS Rank,
                            a.age_name as entreguepor,
                            it.preco
                        FROM
                            [ssojob].[dbo].[tmpDistribuicaoEPI2] as f 
                            INNER JOIN [ssojob].[dbo].[tmpTask] as t on t.tsk_id = f.tsk_id
                            INNER JOIN [ssojob].[dbo].[tmpAgent] as a on a.age_id = t.age_id
                            INNER JOIN [ssojob].[dbo].[tmpPIVOT_Item] as it on it.ite_id = f.ite_id
                    ) as s1
                    INNER JOIN [CorporeRM_JOB].[dbo].[PFUNC] as f on f.CHAPA = cp_matricula
                    INNER JOIN [CorporeRM_JOB].[dbo].[PFUNCAO] as p on f.CODFUNCAO = p.CODIGO AND f.CODCOLIGADA = p.CODCOLIGADA
                    WHERE
                        [cp_matricula] LIKE ? 
                        AND hty_id IN ( SELECT hty_id FROM [ssojob].[dbo].[fichageradas] )
                    ORDER BY cp_data ASC";
        
        $params = array($chapa);
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }
        
        $dados = array();
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Converte objetos DateTime para string
            if (isset($row['cp_data']) && $row['cp_data'] instanceof DateTime) {
                $row['cp_data'] = $row['cp_data']->format('Y-m-d H:i:s');
            }
            if (isset($row['tsk_lastexecutiondatehour']) && $row['tsk_lastexecutiondatehour'] instanceof DateTime) {
                $row['tsk_lastexecutiondatehour'] = $row['tsk_lastexecutiondatehour']->format('Y-m-d H:i:s');
            }
            $dados[] = $row;
        }
        
        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
        
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Endpoint para gerar PDF do comprovante
if (isset($_POST['gerarPDF']) || (isset($_GET['gerarPDF']) && $_GET['gerarPDF'] == '1')) {
    try {
        // Debug: verifica se os dados estão chegando
        error_log("PDF endpoint chamado. POST: " . print_r($_POST, true));
        
        $dadosColaborador = json_decode($_POST['dadosColaborador'], true);
        $registrosEPIs = json_decode($_POST['registrosEPIs'], true);
        
        if (!$dadosColaborador || !$registrosEPIs) {
            throw new Exception('Dados do colaborador ou registros de EPIs não fornecidos');
        }
        
        // Busca os dados dos EPIs do banco de dados (mesmos dados do DataTable)
        $episData = [];
        $dadosColaboradorAtualizados = $dadosColaborador;
        
        try {
            $conn = sqlsrv_connect($serverName, $connectionOptions);
            
            if ($conn === false) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
            
            // Usa a mesma query do DataTable que já está funcionando corretamente
            $sql = "SELECT DISTINCT
                        [hty_id],
                        NOMECOLABORADOR,
                        cp_matricula,
                        FUNCAO,
                        tsk_lastexecutiondatehour,
                        cp_assinatura, 
                        cp_data, 
                        f.CODCOLIGADA as 'coligada',
                        ite_description,
                        cp_quantidade,
                        cp_ca,
                        entreguepor,
                        preco
                    FROM
                        (
                            SELECT
                                f.[hty_id],
                                f.[htv_executiongroup],
                                f.[tsk_id],
                                t.[tss_id],
                                it.[ite_id],
                                t.[tsk_lastexecutiondatehour],
                                it.[ite_description],
                                f.[cp_quantidade],
                                f.[cp_ca],
                                f.[cp_assinatura],
                                [cp_colaborador] AS NOMECOLABORADOR,           
                                [cp_data],
                                [cp_matricula],
                                RANK() OVER (
                                    PARTITION BY [cp_matricula],
                                    f.ite_id
                                    ORDER BY
                                       f.[tsk_lastexecutiondatehour] DESC
                                ) AS Rank,
                                a.age_name as entreguepor,
                                it.preco
                            FROM
                                [ssojob].[dbo].[tmpDistribuicaoEPI2] as f 
                                INNER JOIN [ssojob].[dbo].[tmpTask] as t on t.tsk_id = f.tsk_id
                                INNER JOIN [ssojob].[dbo].[tmpAgent] as a on a.age_id = t.age_id
                                INNER JOIN [ssojob].[dbo].[tmpPIVOT_Item] as it on it.ite_id = f.ite_id
                        ) as s1
                        INNER JOIN [CorporeRM_JOB].[dbo].[PFUNC] as f on f.CHAPA = cp_matricula
                        INNER JOIN [CorporeRM_JOB].[dbo].[PFUNCAO] as p on f.CODFUNCAO = p.CODIGO AND f.CODCOLIGADA = p.CODCOLIGADA
                    WHERE
                        [cp_matricula] LIKE ? 
                        AND hty_id IN ( SELECT hty_id FROM [ssojob].[dbo].[fichageradas] )
                    ORDER BY cp_data ASC";
            
            $params = array($dadosColaborador['cp_matricula']);
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt === false) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
            
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                // Converte objetos DateTime para string
                if (isset($row['cp_data']) && $row['cp_data'] instanceof DateTime) {
                    $row['cp_data'] = $row['cp_data']->format('Y-m-d H:i:s');
                }
                if (isset($row['tsk_lastexecutiondatehour']) && $row['tsk_lastexecutiondatehour'] instanceof DateTime) {
                    $row['tsk_lastexecutiondatehour'] = $row['tsk_lastexecutiondatehour']->format('Y-m-d H:i:s');
                }
                
                // Estrutura os dados no formato esperado pela função gerarHTMLComprovante
                $epiData = [
                    'cp_quantidade' => [
                        'valueForExibition' => $row['cp_quantidade']
                    ],
                    'cp_item' => [
                        'valueForExibition' => $row['ite_description']
                    ],
                    'cp_ca' => [
                        'valueForExibition' => $row['cp_ca']
                    ],
                    'data_especifica' => $row['cp_data'],
                    'assinatura_especifica' => $row['cp_assinatura']
                ];
                
                $episData[] = $epiData;
            }
            
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);
            
    } catch (Exception $e) {
            error_log("Erro ao buscar dados dos EPIs: " . $e->getMessage());
            throw new Exception("Erro ao buscar dados dos EPIs: " . $e->getMessage());
        }
        
        // Gera o HTML do PDF usando os dados dos EPIs coletados
        $html = gerarHTMLComprovante($dadosColaboradorAtualizados, $episData);
        
        // Gera o PDF usando uma biblioteca (aqui você pode usar TCPDF, mPDF, etc.)
        $pdfContent = gerarPDF($html);
        
        // Salva o PDF temporariamente
        $filename = 'comprovante_epi_' . $dadosColaborador['cp_matricula'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $filepath = 'temp/' . $filename;
        
        // Cria diretório temp se não existir
        if (!is_dir('temp')) {
            mkdir('temp', 0755, true);
        }
        
        file_put_contents($filepath, $pdfContent);
        
        echo json_encode([
            'success' => true,
            'message' => 'PDF gerado com sucesso',
            'pdfUrl' => $filepath,
            'filename' => $filename
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Função para gerar HTML do comprovante
function gerarHTMLComprovante($dadosColaborador, $episData) {
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante EPI</title>
    <style>
        /* Estilos para assinatura na tabela */
        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 12px; /* Reduzido de ~15px para 12px (20% menor) */
        }
        .container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }
        .float-right {
            float: right;
        }
        .float-left {
            float: left;
        }
        .clear {
            clear: both;
        }
        .header {
            margin-top: 12px; /* Reduzido ainda mais */
            padding: 0.6rem; /* Reduzido ainda mais */
            border: 0.5px solid black; /* Borda mais fina */
        }
        .header-title {
            /* Remove flexbox - mPDF não suporta bem */
            overflow: hidden; /* Clearfix para o float */
        }
        .header-title img {
            float: left; /* Logo flutua à esquerda */
            margin-right: 15px; /* Espaçamento entre logo e título */
            vertical-align: middle; /* Alinha verticalmente */
        }
        .header-title h1 {
            display: inline-block; /* Permite ficar ao lado do logo */
            font-size: 16px; /* Reduzido ainda mais */
            text-align: left; /* Alinha à esquerda */
            margin: 0; /* Remove todas as margens */
            padding: 0; /* Remove padding */
            line-height: 1.2; /* Reduz altura da linha */
            vertical-align: middle; /* Alinha verticalmente com o logo */
        }
        .header-info h2 {
            font-size: 14px; /* Reduzido de 18px (20% menor) */
            margin-bottom: 4px; /* Reduzido de 5px */
        }
        .header-info p {
            display: inline-block;
            margin: 0;
            font-size: 12px; /* Adicionado para controlar tamanho */
        }
        table.table_user {
            margin-top: 8px; /* Reduzido de 10px */
            width: 100%;
            /* border: 0.5px solid black; */ /* Removido para borda só no header */
            border-collapse: collapse; /* Força o colapso das bordas */
        }
        table { 
            border-collapse: collapse; 
        }
        table thead {
            border-bottom: 0.5px solid black!important; /* Borda apenas embaixo do header */
            padding: 8px; /* Reduzido de 10px */
            height: 40px; /* Reduzido de 50px */
        }
        table.table_user th {
            text-align: left;
            font-weight: bold;
            font-size: 14px; /* Reduzido de 17px (20% menor) */
            letter-spacing: -1px;
            /* border: 0.5px solid black !important; */ /* Removido para borda só em volta */
            padding: 8px; /* Adiciona padding nos headers */
        }
        table.table_user thead tr {
            border: 1px solid black;
        }
        table.table_user td {
            font-size: 14px; /* Reduzido de 17px (20% menor) */
            font-weight: 500; 
            padding: 8px; /* Adiciona padding nas células */
            border: none; /* Remove bordas das células */
        }
        table td {
            font-size: 14px; /* Reduzido de 17px (20% menor) */
            font-weight: 500; 
            padding: 4px 0px; /* Reduzido de 5px */
        }
        .description p {
            font-size: 13px; /* Reduzido de 16.5px (20% menor) */
            letter-spacing: 1px;
            font-weight: bold;
            text-indent: 64px; /* Reduzido de 80px (20% menor) */
        }
        table.table_epi {
            margin-top: 8px; /* Reduzido de 10px */
            width: 100%;
            /* border: 0.5px solid black; */ /* Removido para borda só no header */
            border-collapse: collapse; /* Força o colapso das bordas */
        }
        table.table_epi thead tr {
            border: 1px solid black;
        }
        table.table_epi th {
            font-weight: bold;
            font-size: 14px; /* Reduzido de 17px (20% menor) */
            letter-spacing: -1px;
            /* border: 0.5px solid black !important; */ /* Removido para borda só em volta */
            padding: 8px; /* Adiciona padding nos headers */
        }
        table.table_epi td {
            padding: 8px; /* Adiciona padding nas células */
            border: none; /* Remove bordas das células */
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-title">
                ' . gerarHeaderLogo($dadosColaborador) . '
                <h1>COMPROVANTE DE ENTREGA DE EPI</h1>
            </div>
            <div class="header-info">
                ' . gerarHeaderInfo($dadosColaborador) . '
            </div>
        </div>
        
        <table class="table_user">
            <thead>
                <tr>
                    <th>CHAPA</th>
                    <th>NOME</th>
                    <th>FUNÇÃO</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>' . htmlspecialchars($dadosColaborador['cp_matricula']) . '</td>
                    <td>' . htmlspecialchars($dadosColaborador['NOMECOLABORADOR']) . '</td>
                    <td>' . htmlspecialchars($dadosColaborador['FUNCAO']) . '</td>
                </tr>
            </tbody>
        </table>
        
        <div class="description">
            <p>Declaro para fins de direito que recebi no mês em epígrafe os Equipamentos de Proteção individual abaixo relacionados, fornecidos gratuitamente pela empresa e destinados exclusivamente a minha proteção contra Acidentes e/ou doenças, nos termos Art.191 da C.L.T. e com a NR-6 da Portaria nº 877/18.</p>
            <p>Nos termos do Art. 462 §1º da CLT, a não devolução do equipamento anterior ou constatado dano por mau uso ou negligência comprovada, devo arcar com o custo de reposição do mesmo.</p>
        </div>
        
        <table class="table_epi">
            <thead>
                <tr>
                    <th align="left">DATA</th>
                    <th align="left">QUANT</th>
                    <th align="left">DESCRIÇÃO DO EPI</th>
                    <th>CA</th>
                    <th align="center">ASSINATURA</th>
                </tr>
            </thead>
            <tbody>';
    
    // Adiciona as linhas dos EPIs baseado na estrutura real do XML
    foreach ($episData as $epi) {
        $quantidade = isset($epi['cp_quantidade']['valueForExibition']) ? $epi['cp_quantidade']['valueForExibition'] : '';
        $descricao = isset($epi['cp_item']['valueForExibition']) ? $epi['cp_item']['valueForExibition'] : '';
        $ca = isset($epi['cp_ca']['valueForExibition']) ? $epi['cp_ca']['valueForExibition'] : '';
        
        // Data específica deste EPI (se disponível) ou data geral
        $dataEntrega = '';
        if (isset($epi['data_especifica'])) {
            $dataEntrega = date('d/m/Y', strtotime($epi['data_especifica']));
        } else {
            $dataEntrega = isset($dadosColaborador['cp_data']) ? 
                date('d/m/Y', strtotime($dadosColaborador['cp_data'])) : 
                (isset($dadosColaborador['tsk_lastexecutiondatehour']) ? 
                    date('d/m/Y', strtotime($dadosColaborador['tsk_lastexecutiondatehour'])) : 
                    date('d/m/Y'));
        }
        
        // Assinatura específica deste EPI (se disponível) ou assinatura geral
        $assinatura = '';
        if (isset($epi['assinatura_especifica'])) {
            $assinatura = $epi['assinatura_especifica'];
        } else {
            $assinatura = isset($dadosColaborador['cp_assinatura']) ? $dadosColaborador['cp_assinatura'] : '';
        }
        
        $assinaturaHtml = '';
        if ($assinatura && !empty($assinatura)) {
            // Converte a assinatura para base64
            $assinaturaBase64 = imagemParaBase64($assinatura);
            if ($assinaturaBase64) {
                $assinaturaHtml = '<img src="' . $assinaturaBase64 . '" style="max-width: 80px; max-height: 40px;"/>';
            } else {
                // Fallback se não conseguir baixar a imagem
                $assinaturaHtml = '<div style="width: 80px; height: 40px; border: 1px solid #ccc; text-align: center; line-height: 40px; font-size: 10px;">Assinatura</div>';
            }
        }
        
        $html .= '<tr>
                    <td align="center">' . htmlspecialchars($dataEntrega) . '</td>
                    <td align="center">' . htmlspecialchars($quantidade) . '</td>
                    <td>' . htmlspecialchars($descricao) . '</td>
                    <td>' . htmlspecialchars($ca) . '</td>
                    <td align="center">' . $assinaturaHtml . '</td>
                </tr>';
    }
    
    $html .= '</tbody>
        </table>
    </div>
</body>
</html>';
    
    return $html;
}

// Função helper para converter imagem URL para base64
function imagemParaBase64($url) {
    $logFile = __DIR__ . '/temp/debug_images.log';
    $log = "\n=== " . date('Y-m-d H:i:s') . " === Tentando baixar: $url\n";
    
    try {
        // Primeiro tenta com cURL (mais robusto)
        if (function_exists('curl_init')) {
            $log .= "cURL está disponível\n";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $log .= "cURL HTTP Code: $httpCode\n";
            if ($error) {
                $log .= "cURL Error: $error\n";
            }
            
            if ($httpCode === 200 && $imageData !== false && !empty($imageData)) {
                $log .= "✓ SUCESSO! Tamanho: " . strlen($imageData) . " bytes\n";
                
                // Detecta o tipo MIME da imagem
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($imageData);
                $log .= "Tipo MIME detectado: $mimeType\n";
                
                file_put_contents($logFile, $log, FILE_APPEND);
                return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            } else {
                $log .= "✗ cURL falhou: HTTP $httpCode, dados vazios? " . (empty($imageData) ? 'SIM' : 'NÃO') . "\n";
            }
        } else {
            $log .= "✗ cURL NÃO disponível\n";
        }
        
        // Fallback: tenta com file_get_contents
        $log .= "Tentando file_get_contents...\n";
        $imageData = @file_get_contents($url);
        if ($imageData !== false && !empty($imageData)) {
            $log .= "✓ file_get_contents sucesso! Tamanho: " . strlen($imageData) . " bytes\n";
            
            // Detecta o tipo MIME da imagem
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageData);
            $log .= "Tipo MIME detectado: $mimeType\n";
            
            file_put_contents($logFile, $log, FILE_APPEND);
            return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
        } else {
            $log .= "✗ file_get_contents falhou\n";
        }
        
    } catch (Exception $e) {
        $log .= "✗ EXCEÇÃO: " . $e->getMessage() . "\n";
    }
    
    $log .= "✗✗✗ FALHA TOTAL\n";
    file_put_contents($logFile, $log, FILE_APPEND);
    return '';
}

// Função para gerar logo do header baseado na coligada
function gerarHeaderLogo($dadosColaborador) {
    $coligada = isset($dadosColaborador['coligada']) ? $dadosColaborador['coligada'] : '1';
    
    if ($coligada == '2') {
        $logoUrl = 'https://whitelabel.umov.me/tecsadiag2017/CENTER_LOGO?1503957675071';
    } else {
        // Coligada 1 (padrão)
        $logoUrl = 'https://whitelabel.umov.me/ssojob/CENTER_LOGO?1663176199143';
    }
    
    $logoBase64 = imagemParaBase64($logoUrl);
    if ($logoBase64) {
        return '<img src="' . $logoBase64 . '" width="80px" height="50px" style="vertical-align: middle;" />';
    }
    return ''; // Retorna vazio se não conseguir carregar
}

// Função para gerar informações do header baseado na coligada
function gerarHeaderInfo($dadosColaborador) {
    $coligada = isset($dadosColaborador['coligada']) ? $dadosColaborador['coligada'] : '1';
    
    if ($coligada == '2') {
        return '<h2>TECSA SERVICOS LTDA.</h2>
                <p>58.780.966/0001-79</p>';
    } else {
        // Coligada 1 (padrão)
        return '<h2>JOB ENGENHARIA E SERVICOS LTDA.</h2>
                <p>54.522.867/0001-36</p>';
    }
}

// Função para gerar PDF usando mPDF
function gerarPDF($html) {
    try {
        // Cria uma instância do mPDF
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_header' => 9,
            'margin_footer' => 9,
            'tempDir' => 'temp/'
        ]);
        
        // Define metadados do PDF
        $mpdf->SetTitle('Comprovante de Entrega de EPI');
        $mpdf->SetAuthor('Sistema SSO');
        $mpdf->SetCreator('Sistema SSO');
        $mpdf->SetSubject('Comprovante de Entrega de EPI');
        
        // Adiciona o HTML ao PDF
        $mpdf->WriteHTML($html);
        
        // Gera o PDF como string
        $pdfContent = $mpdf->Output('', 'S');
        
        return $pdfContent;

} catch (Exception $e) {
        error_log("Erro ao gerar PDF: " . $e->getMessage());
        throw new Exception("Erro ao gerar PDF: " . $e->getMessage());
    }
}

// Se nenhum endpoint específico foi chamado
http_response_code(400);
echo json_encode(['error' => 'Endpoint não encontrado']);
?>
