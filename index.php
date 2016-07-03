<?php

// Email configuration

# Gmail settings: https://www.google.com/settings/security/lesssecureapps
$hostname = '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX';
$usermail = 'email@gmail.com';
$pwemail  = 'password';

// Database configuration
$server   = '';
$username = '';
$password = '';
$database = '';
 
$inbox = imap_open($hostname, $usermail, $pwemail) or 
            die('Email: '.$usermail. '<br>'.
                'Password: ' .$pwemail. '<br>'.
                'Not conected: ' . imap_last_error());

$timestamp = date("d-m-Y H:i:s");
echo('<p>Started: ' . $timestamp);

$headers = imap_headers($inbox);

// http://php.net/manual/pt_BR/function.imap-search.php
// Only unseen messages. For all messages type "ALL"
$emails = imap_search($inbox,'UNSEEN'); 

/* if any emails found, iterate through each email */
if($emails) {
    
    $count = 1;
    
    /* put the newest emails on top */
    rsort($emails);
    
    /* for every email... */
    foreach($emails as $email_number) 
    {

        /* get information specific to this email */
        $overview = imap_fetch_overview($inbox,$email_number,0);
        
        /* get mail message */
        $message = imap_fetchbody($inbox,$email_number,2);
        
        /* get mail structure */
        $structure = imap_fetchstructure($inbox, $email_number);

        /* cabecalho do email */
        $header = imap_header($inbox, $email_number);

        /* email do remetente */
        $recebido_por = $header->from[0]->mailbox . "@" . $header->from[0]->host;

        $attachments = array();
        
        /* if any attachments found... */
        if(isset($structure->parts) && count($structure->parts)) 
        {
            for($i = 0; $i < count($structure->parts); $i++) 
            {
                $attachments[$i] = array(
                    'is_attachment' => false,
                    'filename' => '',
                    'name' => '',
                    'attachment' => ''
                );
            
                if($structure->parts[$i]->ifdparameters) 
                {
                    foreach($structure->parts[$i]->dparameters as $object) 
                    {
                        if(strtolower($object->attribute) == 'filename') 
                        {
                            $extensao = substr($object->value, -4);
                            
                            if(strtolower($extensao) == '.xml') 
                            {
                                $attachments[$i]['is_attachment'] = true;
                                $attachments[$i]['filename'] = $object->value;
                            }
                        }
                    }
                }
            
                if($structure->parts[$i]->ifparameters) 
                {
                    foreach($structure->parts[$i]->parameters as $object) 
                    {
                        if(strtolower($object->attribute) == 'name') 
                        {
                            $extensao = substr($object->value, -4);
                            
                            if(strtolower($extensao) == '.xml') 
                            {
                                $attachments[$i]['is_attachment'] = true;
                                $attachments[$i]['name'] = $object->value;
                            }
                        }
                    }
                }
            
                if($attachments[$i]['is_attachment']) 
                {
                    $attachments[$i]['attachment'] = imap_fetchbody($inbox, $email_number, $i+1);
                    
                    /* 3 = BASE64 encoding */
                    if($structure->parts[$i]->encoding == 3) 
                    { 
                        $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                    }

                    /* 4 = QUOTED-PRINTABLE encoding */
                    elseif($structure->parts[$i]->encoding == 4) 
                    { 
                        $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                    }
                }
            }
        }
        
        /* iterate through each attachment and save it */
        foreach($attachments as $attachment)
        {
            $xml = '';
            $chave = '';
            $emit_nome = '';
            $emit_cnpj = '';
            $dest_cnpj = '';

            if($attachment['is_attachment'] == 1)
            {
                $filename = "./xml/" . $attachment['name'];
                if(empty($filename)) $filename = $attachment['filename'];
                
                // save file in ftp
                $ftp = fopen($filename, "w+");
                fwrite($ftp, $attachment['attachment']);
                fclose($ftp);

                $xml = simplexml_load_file($filename); 
                $conteudo_xml = file_get_contents($filename);

                $chave = $xml->protNFe->infProt->chNFe;
                if($chave == ''){
                    $chave = $xml->protNFe->infProt->chNFe;
                }

                $emit_nome = $xml->NFe->infNFe->emit->xNome;
                if($emit_nome == ''){
                    $emit_nome = $xml->infNFe->emit->xNome;
                }

                $emit_cnpj = $xml->NFe->infNFe->emit->CNPJ;
                if($emit_cnpj == ''){
                    $emit_cnpj = $xml->infNFe->emit->CNPJ;
                }

                $dest_cnpj = $xml->NFe->infNFe->dest->CNPJ;
                if($dest_cnpj == ''){
                    $dest_cnpj = $xml->infNFe->dest->CNPJ;
                }
                
                if($dest_cnpj == ''){
                    $dest_cnpj = $xml->NFe->infNFe->dest->CPF;
                }

                $data_emi = $xml->NFe->infNFe->ide->dhEmi;
                if($data_emi == ''){
                    $data_emi = $xml->NFe->infNFe->ide->dEmi;
                }

                // Show info
                echo('<p>');
                echo(' Chave de acesso: ' . $chave              . '<br />');
                echo(' Recebido por: '    . $recebido_por       . '<br />');
                echo(' Nome arquivo: '    . $filename           . '<br />');
                echo(' Data emissao: '    . $data_emi           . '<br />');
                echo(' Nome emit: '       . $emit_nome          . '<br />');
                echo(' CNPJ emit: '       . $emit_cnpj          . '<br />');
                echo(' CNPJ/CPF dest: '   . $dest_cnpj          . '<br />');
                echo(' Data importacao: ' . date("d-m-Y H:i:s") . '<br />');
                echo('</p>');

                // Send to database
                $conexao = mysqli_connect($server, $username, $password, $database);
                if ($conexao->connect_error) {
                    die("Connection failed: " . $conexao->connect_error);
                }

                $sql = "select * from XML_NF where chave_acesso = '{$chave}' ";
                $result = mysqli_query($conexao, $sql);
                $numrow = mysqli_num_rows($result);

                if ($numrow <= 0) {
                    if ($chave != '') {
                        $data_importacao = date('d-m-Y H:i:s');
                        $sqlInsert = "insert into XML_NF (chave_acesso, conteudo_xml, recebido_por, emit_nome, emit_cnpj, dest_cnpj, data_emi) ";
                        $sqlInsert = $sqlInsert . "values ( '{$chave}', ";
                        $sqlInsert = $sqlInsert . "         '{$conteudo_xml}', ";
                        $sqlInsert = $sqlInsert . "         '{$recebido_por}', ";
                        $sqlInsert = $sqlInsert . "         '{$emit_nome}', ";
                        $sqlInsert = $sqlInsert . "         '{$emit_cnpj}', ";
                        $sqlInsert = $sqlInsert . "         '{$dest_cnpj}', ";
                        $sqlInsert = $sqlInsert . "         '{$data_emi}') ";

                        mysqli_query($conexao, $sqlInsert);
                    }
                }
                else {
                    echo "XML already exists!<br>";
                }
            }
        }        
    }    
} 

imap_close($inbox);

$timestamp = date("d-m-Y H:i:s");
echo('<p>Finished: ' . $timestamp);

?>