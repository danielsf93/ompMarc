<?php

    /**
     * FUNÇÃO PRINCIPAL, RESPOSÁVEL PELA ESTRUTURA DO ARQUIVO mrk.
     */
    public function exportSubmissions($submissionIds, $context, $user, $request)
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
        $submissions = [];
        $app = new Application();
        $request = $app->getRequest();
        $press = $request->getContext();

        /********************************************		FOREACH'S	********************************************/
        foreach ($submissionIds as $submissionId) {
            $submission = $submissionDao->getById($submissionId, $context->getId());
            if ($submission) {
                $submissions[] = $submission;
            }
        }
        // Obtendo dados dos autores
            $authorNames = [];
            $authors = $submission->getAuthors();

        // Obtendo informações do primeiro autor
        $firstAuthor = reset($authors);

        foreach ($authors as $author) {
            $authorInfo = [
                'givenName' => $author->getLocalizedGivenName(),
                'surname' => $author->getLocalizedFamilyName(),
                'orcid' => $author->getOrcid(),
                'afiliation' => $author->getLocalizedAffiliation(),
                'locale' => $author->getCountryLocalized(),
            ];
            $authorsInfo[] = $authorInfo;
        }

        foreach ($submissions as $submission) {
            // Obtendo o título da submissão
            $submissionTitle = $submission->getLocalizedFullTitle();
            //obtendo o tipo de conteudo, capitulo e monografia. crossref só aceita "edited_book, monograph, reference, other" porém ao iniciar uma nova publicação, só há entrada para 'monograph' e 'other'
            $types = [1 => 'other', 2 => 'monograph', 3 => 'other', 4 => 'other'];
            $type = $submission->getWorkType();

            $abstract = $submission->getLocalizedAbstract();
            $doi = $submission->getStoredPubId('doi');
            $publicationUrl = $request->url($context->getPath(), 'catalog', 'book', [$submission->getId()]);
            $copyright = $submission->getLocalizedcopyrightHolder();
            $copyrightyear = $submission->getCopyrightYear();
            // aqui retorna ano mes dia $publicationYear = $submission->getDatePublished();
            $publicationDate = $submission->getDatePublished();
            $publicationYear = date('Y', strtotime($publicationDate));
            $publicationMonth = date('m', strtotime($publicationDate));
            $publicationDay = date('d', strtotime($publicationDate));
                          
            // Obtendo dados dos autores
            $authorNames = [];
            $authors = $submission->getAuthors();
        foreach ($authors as $author) {
            $givenName = $author->getLocalizedGivenName();
            $surname = $author->getLocalizedFamilyName();
            // $afiliation = $author->getLocalizedAffiliation();
            $authorNames[] = $givenName.' '.$surname;
            }
            $authorName = implode(', ', $authorNames);

            $isbn = '';
            $publicationFormats = $submission->getCurrentPublication()->getData('publicationFormats');
        foreach ($publicationFormats as $publicationFormat) {
                $identificationCodes = $publicationFormat->getIdentificationCodes();
                while ($identificationCode = $identificationCodes->next()) {
                    if ($identificationCode->getCode() == '02' || $identificationCode->getCode() == '15') {
                        // 02 e 15: códigos ONIX para ISBN-10 ou ISBN-13
                        $isbn = $identificationCode->getValue();
                        break; // Encerra o loop ao encontrar o ISBN
                    }
                }
            }
    ///// ESTRUTURA MRC

    $currentDateTime = date('YmdHis.0');
    $zeroZeroCinco = ''."{$currentDateTime}";
    $zeroZeroOito = ''.'230919s2023    bl            000 0 por d';
   
    $cleanIsbn = preg_replace('/[^0-9]/', '', $isbn);
    $zeroDoisZero = '  a'.htmlspecialchars($cleanIsbn).'7 ';
    $zeroDoisQuatro = 'a'.htmlspecialchars($doi).'2DOI';
    $zeroQuatroZero = '  aUSP/ABCD0 ';
    $zeroQuatroUm ='apor  ';
    $zeroQuatroQuatro = 'abl1 ';

//primeiro autor - Sobrenome, Nome - Orcid - Afiliação - País
$firstAuthor = reset($authorsInfo);

if (!empty($firstAuthor['orcid']) && !empty($firstAuthor['afiliation'])) {
    $umZeroZero = 'a' . htmlspecialchars($firstAuthor['surname']) . ', ' . htmlspecialchars($firstAuthor['givenName']) . 
                    '0' . $firstAuthor['orcid'] . 
                    '5(*)7INT8' . htmlspecialchars($firstAuthor['afiliation']) . '9' . htmlspecialchars($firstAuthor['locale']);
} elseif (!empty($firstAuthor['orcid'])) {
    // Adiciona apenas o ORCID se presente
    $umZeroZero = 'a' . htmlspecialchars($firstAuthor['surname']) . ', ' . htmlspecialchars($firstAuthor['givenName']) . 
                    '0' . $firstAuthor['orcid'] . 
                    '5(*)7INT9' . htmlspecialchars($firstAuthor['locale']);
} elseif (!empty($firstAuthor['afiliation'])) {
    // Adiciona apenas a afiliação se presente
    $umZeroZero = 'a' . htmlspecialchars($firstAuthor['surname']) . ', ' . htmlspecialchars($firstAuthor['givenName']) . 
                    '7INT8' . htmlspecialchars($firstAuthor['afiliation']) . '9' . htmlspecialchars($firstAuthor['locale']);
} else {
    // Adiciona sem ORCID e afiliação se nenhum estiver presente
    $umZeroZero= 'a' . htmlspecialchars($firstAuthor['surname']) . ', ' . htmlspecialchars($firstAuthor['givenName']) . 
                    '5(*)9' . htmlspecialchars($firstAuthor['locale']);
}

// Adiciona uma quebra de linha no final
//$marcContent .= PHP_EOL;

    //titulo
    $doisQuatroCinco = '12a'.htmlspecialchars($submissionTitle).'h[recurso eletrônico]  ';
    
    
    //Campo 260 local e copyright
$cidade = $this->obterCidade($copyright);
if (strpos($copyright, 'Universidade de São Paulo. ') === 0) {
    $copyright = substr($copyright, strlen('Universidade de São Paulo. '));
}
    $doisMeiaZero = 'a ' . $cidade . 'b' . htmlspecialchars($copyright) . 'c' . htmlspecialchars($copyrightyear) .'  ';

$currentDateTime = date('d.m.Y');
    $cincoZeroZero=  'aDisponível em: '.htmlspecialchars($publicationUrl) . '. Acesso em: '.$currentDateTime;
    
   // Demais autoras - Sobrenome, Nome - Orcid - Afiliação - País
$additionalAuthors = array_slice($authorsInfo, 1); // Pular o primeiro autor

$seteZeroZero = ''; // Inicializa a string vazia

foreach ($additionalAuthors as $additionalAuthor) {
    $additionalAuthorInfo = [
        'givenName' => $additionalAuthor['givenName'],
        'surname' => $additionalAuthor['surname'],
        'orcid' => $additionalAuthor['orcid'],
        'afiliation' => $additionalAuthor['afiliation'],
        'locale' => $additionalAuthor['locale'],
    ];

    $authorString = '';

    if (!empty($additionalAuthorInfo['orcid']) && !empty($additionalAuthorInfo['afiliation'])) {
        $authorString = '1 a' . htmlspecialchars($additionalAuthorInfo['surname']) . ', ' . htmlspecialchars($additionalAuthorInfo['givenName']) . '0' . $additionalAuthorInfo['orcid'] . '4colab5(*)7INT8' . htmlspecialchars($additionalAuthorInfo['afiliation']) . '9' . htmlspecialchars($additionalAuthorInfo['locale']);
    } elseif (!empty($additionalAuthorInfo['orcid'])) {
        // Adiciona apenas o ORCID se presente
        $authorString = '1 a' . htmlspecialchars($additionalAuthorInfo['surname']) . ', ' . htmlspecialchars($additionalAuthorInfo['givenName']) . '0' . $additionalAuthorInfo['orcid'] . '4colab5(*)$7INT9' . htmlspecialchars($additionalAuthorInfo['locale']);
    } elseif (!empty($additionalAuthorInfo['afiliation'])) {
        // Adiciona apenas a afiliação se presente
        $authorString = '1 a' . htmlspecialchars($additionalAuthorInfo['surname']) . ', ' . htmlspecialchars($additionalAuthorInfo['givenName']) . '4colab5(*)7INT8' . htmlspecialchars($additionalAuthorInfo['afiliation']) . '9' . htmlspecialchars($additionalAuthorInfo['locale']);
    } else {
        // Adiciona sem ORCID e afiliação se nenhum estiver presente
        $authorString = '1 a' . htmlspecialchars($additionalAuthorInfo['surname']) . ', ' . htmlspecialchars($additionalAuthorInfo['givenName']) . '4colab5(*)9' . htmlspecialchars($additionalAuthorInfo['locale']);
    }

    // Concatena a string do autor adicional à string geral
    $seteZeroZero .= $authorString;
}


    //harcoding's abcd usp

    $oitoCincoMeiaA = '4 zClicar sobre o botão para acesso ao texto completo'.
    'u'.'https://doi.org/'.htmlspecialchars($doi).'3DOI';
    $oitoCincoMeiaB = '41z'.'Clicar sobre o botão para acesso ao texto completou'.
    htmlspecialchars($publicationUrl).'3E-Livro  ';

    $noveQuatroCinco = 'aPbMONOGRAFIA/LIVROc06j2023lNACIONAL';

    
//Organizando a numeração rec005 = campo 005, rec008 = campo 008, etc.

$fixa = 0;
$rec005POS = $fixa;
$rec005CAR = sprintf('%04d', strlen($zeroZeroCinco) + 0);
$rec005 = '005' . $rec005CAR . sprintf('%05d', $rec005POS);

$rec008POS = sprintf('%05d', $rec005CAR + $rec005POS);
$rec008CAR = sprintf('%04d', strlen($zeroZeroOito) + 0);
$rec008 = '008' . $rec008CAR . $rec008POS;

$rec020POS = sprintf('%05d', $rec008CAR + $rec008POS);
$rec020CAR = sprintf('%04d', strlen($zeroDoisZero) - 3);
$rec020 = '020' . $rec020CAR . $rec020POS;

$rec024POS = sprintf('%05d', $rec020CAR + $rec020POS);
$rec024CAR = sprintf('%04d', strlen($zeroDoisQuatro) + 3);
$rec024 = '024' . $rec024CAR . $rec024POS;

$rec040POS = sprintf('%05d', $rec024CAR + $rec024POS);
$rec040CAR = sprintf('%04d', strlen($zeroQuatroZero) - 3);
$rec040 = '040' . $rec040CAR . $rec040POS;

$rec041POS = sprintf('%05d', $rec040CAR + $rec040POS);
$rec041CAR = sprintf('%04d', strlen($zeroQuatroUm) + 0);
$rec041 = '041' . $rec041CAR . $rec041POS;

$rec044POS = sprintf('%05d', $rec041CAR + $rec041POS);
$rec044CAR = sprintf('%04d', strlen($zeroQuatroQuatro) + 0);
$rec044 = '044' . $rec044CAR . $rec044POS;

$rec100POS = sprintf('%05d', $rec044CAR + $rec044POS);
$rec100CAR = sprintf('%04d', strlen($umZeroZero) + 3);
$rec100 = '100' . $rec100CAR . $rec100POS;

$rec245POS = sprintf('%05d', $rec100CAR + $rec100POS);
$rec245CAR = sprintf('%04d', strlen($doisQuatroCinco) - 3);
$rec245 = '245' . $rec245CAR . $rec245POS;

//local e copy - vrfcr
$rec260POS = sprintf('%05d', $rec245CAR + $rec245POS);
$rec260CAR = sprintf('%04d', strlen($doisMeiaZero) + 0);
$rec260 = '260' . $rec260CAR . $rec260POS;

$rec500POS = sprintf('%05d', $rec260CAR + $rec260POS);
$rec500CAR = sprintf('%04d', strlen($cincoZeroZero) + 3);
$rec500 = '500' . $rec500CAR . $rec500POS;

// Quantidade de autores adicionais
$numAutoresAdicionais = count($additionalAuthors);
// Criação dos campos 700 para autores adicionais
$rec700 = '';
for ($i = 0; $i < $numAutoresAdicionais; $i++) {
    // Atualiza as posições e comprimentos para cada coautor
    $rec700POS = sprintf('%05d', $rec500CAR + $rec500POS);
    $rec700CAR = sprintf('%04d', strlen($seteZeroZero) + 0);
    $rec700 .= '700' . $rec700CAR . $rec700POS;

    // Atualiza as posições e comprimentos para o próximo coautor (se houver)
    $rec500POS = $rec700POS;
    $rec500CAR = $rec700CAR;
}

// Continuação do código
$rec856APOS = sprintf('%05d', $rec500CAR + $rec500POS);
$rec856ACAR = sprintf('%04d', strlen($oitoCincoMeiaA) - 1);

if ($numAutoresAdicionais > 0) {
    // Se houver coautores, use a posição e o comprimento do último campo 700
    $rec856APOS = sprintf('%05d', $rec700CAR + $rec700POS);
    $rec856ACAR = sprintf('%04d', strlen($oitoCincoMeiaA) - 1);
}

$rec856A = '856' . $rec856ACAR . $rec856APOS;


$rec856BPOS = sprintf('%05d', $rec856ACAR + $rec856APOS);
$rec856BCAR = sprintf('%04d', strlen($oitoCincoMeiaB) - 2);
$rec856B = '856' . $rec856BCAR . $rec856BPOS;

$rec945POS = sprintf('%05d', $rec856BCAR + $rec856BPOS);
$rec945CAR = sprintf('%04d', strlen($noveQuatroCinco) + 1);
$rec945 = '945' . $rec945CAR . $rec945POS;

//colocando a informação no arquivo final
//numeração
$marcContent .= 
$rec005 .
$rec008 .
$rec020 .
$rec024 .
$rec040 .
$rec041 .
$rec044 .
$rec100 .
$rec245 .
$rec260 .
$rec500 .
$rec700 .
$rec856A .
$rec856B .
$rec945;

//texto
$marcContent .= 
$zeroZeroCinco .
$zeroZeroOito . 
$zeroDoisZero . 
$zeroDoisQuatro . 
$zeroQuatroZero . 
$zeroQuatroUm . 
$zeroQuatroQuatro . 
$umZeroZero . 
$doisQuatroCinco . 
$doisMeiaZero . 
$cincoZeroZero . 
$seteZeroZero . 
$oitoCincoMeiaA . 
$oitoCincoMeiaB . 
$noveQuatroCinco;


}
 // Calcular o número de caracteres
$numeroDeCaracteres = mb_strlen($marcContent, 'UTF-8'); 
// Formatar o número de caracteres como uma string de 5 dígitos
$numeroDeCaracteresFormatado = sprintf("%05d", $numeroDeCaracteres);

// Verificar a condição de pelo menos 1 coautor
if ($numAutoresAdicionais > 0) {
    $marcContent = $numeroDeCaracteresFormatado . 'nam 22000205a 4500 '. $marcContent;
} else {
    // Formatar normalmente para outros casos
    $marcContent = $numeroDeCaracteresFormatado . 'nam 22000193a 4500 '. $marcContent;
}





        return $marcContent;
            }

   /**
     * fim ESTRUTURA mrc
    *
                            * */
        
     
}
