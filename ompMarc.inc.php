<?php
//plugins/importexport/ompMarc/ompMarc.inc.php
import('plugins.importexport.ompMarc.lib.pkp.classes.plugins.ImportExportPlugin');

class ompMarc extends ImportExportPlugin2
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return $success;
        }
        if ($success && $this->getEnabled()) {
            $this->addLocaleData();
            $this->import('ompMarcDeployment');
        }

        return $success;
    }

    public function display($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        parent::display($args, $request);

        $templateMgr->assign('plugin', $this);

        switch (array_shift($args)) {
            //aqui monta a página do plugin
            case 'index':
            case '':
                $apiUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'submissions');
                $submissionsListPanel = new \APP\components\listPanels\SubmissionsListPanel(
                    'submissions',
                    __('common.publications'),
                    [
                        'apiUrl' => $apiUrl,
                        'count' => 100,
                        'getParams' => [
                            'status' => [STATUS_PUBLISHED], // Filtro para livros publicados
                        ],
                        'lazyLoad' => true,
                    ]
                );
        
                $submissionsConfig = $submissionsListPanel->getConfig();
                $submissionsConfig['addUrl'] = '';
                $submissionsConfig['filters'] = array_slice($submissionsConfig['filters'], 1);
        
                $templateMgr->setState([
                    'components' => [
                        'submissions' => $submissionsConfig,
                    ],
                ]);
        
                $templateMgr->assign([
                    'pageComponent' => 'ImportExportPage',
                ]);
        
                $templateMgr->display($this->getTemplateResource('index.tpl'));
                break;
                //aqui exporta o livro
                case 'export':
                    $exportXml = $this->exportSubmissions(
                        (array) $request->getUserVar('selectedSubmissions'),
                        $request->getContext(),
                        $request->getUser(),
                        $request
                    );
                    import('lib.pkp.classes.file.FileManager');
                    $fileManager = new FileManager();
                    //nome do arquivo e formato txt - trocar por rec
                    $exportFileName = $this->getExportPath() . '/omp.mrc';
                    $fileManager->writeFile($exportFileName, $exportXml);
                    $fileManager->downloadByPath($exportFileName);
                    $fileManager->deleteByPath($exportFileName);
                    break;
            default:
                $dispatcher = $request->getDispatcher();
                $dispatcher->handle404();
        }
    }

    // forma o link de acesso a ferramenta
    public function getName()
    {
        return 'ompMarc';
    }

    /**
     * Get the display name.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return __('plugins.importexport.ompMarc.displayName');
    }

    /**
     * Get the display description.
     *
     * @return string
     */
    public function getDescription()
    {
        return __('plugins.importexport.ompMarc.description');
    }

    //forma o prefixo do arquivo .xml -desnecessário
    public function getPluginSettingsPrefix()
    {
        return 'ompMarc';
    }
    
    //função que obtém as cidades com base no copyrightholder
    function obterCidade($copyright) {
        $mapeamentoCidades = [
            'São Paulo' => [
                'Escola de Artes, Ciências e Humanidades', 'Escola de Artes, Ciências e Humanidades ', 'Escola de Comunicações e Artes', 'Escola de Comunicações e Artes ', 
                'Escola de Educação Física e Esporte', 'Escola de Educação Física e Esporte ', 'Escola de Enfermagem', 'Escola de Enfermagem ', 
                'Escola Politécnica', 'Escola Politécnica ', 'Faculdade de Arquitetura e Urbanismo', 'Faculdade de Arquitetura e Urbanismo ', 
                'Faculdade de Ciências Farmacêuticas', 'Faculdade de Ciências Farmacêuticas ', 'Faculdade de Direito', 'Faculdade de Direito ', 
                'Faculdade de Economia, Administração e Contabilidade', 'Faculdade de Economia, Administração e Contabilidade ', 
                'Faculdade de Educação', 'Faculdade de Educação ', 'Faculdade de Filosofia, Letras e Ciências Humanas', 'Faculdade de Filosofia, Letras e Ciências Humanas ', 
                'Faculdade de Medicina', 'Faculdade de Medicina ', 'Faculdade de Medicina Veterinária e Zootecnia', 'Faculdade de Medicina Veterinária e Zootecnia ', 
                'Faculdade de Odontologia', 'Faculdade de Odontologia ', 'Faculdade de Saúde Pública', 'Faculdade de Saúde Pública ', 
                'Instituto de Astronomia, Geofísica e Ciências Atmosféricas','Instituto de Astronomia, Geofísica e Ciências Atmosféricas ', 
                'Instituto de Biociências', 'Instituto de Biociências ', 'Instituto de Ciências Biomédicas', 'Instituto de Ciências Biomédicas ', 
                'Instituto de Energia e Ambiente', 'Instituto de Energia e Ambiente ', 'Instituto de Estudos Avançados', 'Instituto de Estudos Avançados ', 
                'Instituto de Estudos Brasileiros', 'Instituto de Estudos Brasileiros ','Instituto de Física', 'Instituto de Física ', 
                'Instituto de Geociências', 'Instituto de Geociências ', 'Instituto de Matemática e Estatística', 'Instituto de Matemática e Estatística ', 
                'Instituto de Medicina Tropical de São Paulo', 'Instituto de Medicina Tropical de São Paulo ', 
                'Instituto de Psicologia', 'Instituto de Psicologia ', 'Instituto de Química', 'Instituto de Química ', 
                'Instituto de Relações Internacionais', 'Instituto de Relações Internacionais ', 
                'Instituto Oceanográfico', 'Instituto Oceanográfico ', 'Museu de Arqueologia e Etnografia', 'Museu de Arqueologia e Etnografia ', 
                'Museu de Arte Contemporânea', 'Museu de Arte Contemporânea ', 'Museu Paulista', 'Museu Paulista ', 
                'Museu de Zoologia', 'Museu de Zoologia ', 
            ],

            'Bauru' => [
                'Faculdade de Odontologia de Bauru', 'Faculdade de Odontologia de Bauru ', 'Hospital de Reabilitação de Anomalias Craniofaciais', 'Hospital de Reabilitação de Anomalias Craniofaciais ',
            ],

            'Lorena' => [
                'Escola de Engenharia de Lorena', 'Escola de Engenharia de Lorena ', 
            ],

            'Piracicaba' => [
                'Centro de Energia Nuclear na Agricultura', 'Centro de Energia Nuclear na Agricultura ', 'Escola Superior de Agricultura “Luiz de Queiroz”', 'Escola Superior de Agricultura “Luiz de Queiroz” ', 'Escola Superior de Agricultura Luiz de Queiroz', 'Escola Superior de Agricultura Luiz de Queiroz ', 
            ],
            
            'Pirassununga' => [
                'Faculdade de Zootecnia e Engenharia de Alimentos', 'Faculdade de Zootecnia e Engenharia de Alimentos ',
            ],
            
            'Ribeirão Preto' => [
                'Escola de Educação Física e Esporte de Ribeirão Preto', 'Escola de Educação Física e Esporte de Ribeirão Preto ', 'Escola de Enfermagem de Ribeirão Preto', 'Escola de Enfermagem de Ribeirão Preto ', 'Faculdade de Ciências Farmacêuticas de Ribeirão Preto', 'Faculdade de Ciências Farmacêuticas de Ribeirão Preto ', 'Faculdade de Direito de Ribeirão Preto', 'Faculdade de Direito de Ribeirão Preto ', 'Faculdade de Economia, Administração e Contabilidade de Ribeirão Preto', 'Faculdade de Economia, Administração e Contabilidade de Ribeirão Preto ', 'Faculdade de Filosofia, Ciências e Letras de Ribeirão Preto', 'Faculdade de Filosofia, Ciências e Letras de Ribeirão Preto ', 'Faculdade de Medicina de Ribeirão Preto', 'Faculdade de Medicina de Ribeirão Preto ', 'Faculdade de Odontologia de Ribeirão Preto', 'Faculdade de Odontologia de Ribeirão Preto ', 
            ],

            'Santos' => [
                'Departamento de Engenharia de Minas e Petróleo', 'Departamento de Engenharia de Minas e Petróleo ',
            ],

            'São Carlos' => [
                'Escola de Engenharia de São Carlos', 'Escola de Engenharia de São Carlos ', 'Instituto de Arquitetura e Urbanismo', 'Instituto de Arquitetura e Urbanismo ', 'Instituto de Ciências Matemáticas e de Computação', 'Instituto de Ciências Matemáticas e de Computação ', 'Instituto de Física de São Carlos', 'Instituto de Física de São Carlos ', 'Instituto de Química de São Carlos', 'Instituto de Química de São Carlos ', 
            ],

        ];
    
        $copyright = str_replace('Universidade de São Paulo. ', '', $copyright);
        foreach ($mapeamentoCidades as $cidade => $variacoes) {
            if (in_array($copyright, $variacoes)) {
                return $cidade;
            }
        }
    
        // Se não houver correspondência, retorna 'LOCAL'
        return 'LOCAL';
    }
    
    
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

///Estrutura numeração MRC

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

    foreach ($additionalAuthors as $additionalAuthor) {
    $additionalAuthorInfo = [
        'givenName' => $additionalAuthor['givenName'],
        'surname' => $additionalAuthor['surname'],
        'orcid' => $additionalAuthor['orcid'],
        'afiliation' => $additionalAuthor['afiliation'],
        'locale' => $additionalAuthor['locale'],
    ];

    if (!empty($additionalAuthorInfo['orcid']) && !empty($additionalAuthorInfo['afiliation'])) {
        $seteZeroZero = 'a' . htmlspecialchars($additionalAuthorInfo['surname']) . ', ' . htmlspecialchars($additionalAuthorInfo['givenName']) . '0' . $additionalAuthorInfo['orcid'] . '$5(*)7INT8' . htmlspecialchars($additionalAuthorInfo['afiliation']) . '9' . htmlspecialchars($additionalAuthorInfo['locale']);
    } elseif (!empty($additionalAuthorInfo['orcid'])) {
        // Adiciona apenas o ORCID se presente
        $seteZeroZero = 'a' . htmlspecialchars($additionalAuthorInfo['surname']) . ', ' . htmlspecialchars($additionalAuthorInfo['givenName']) . '0' . $additionalAuthorInfo['orcid'] . '$5(*)$7INT9' . htmlspecialchars($additionalAuthorInfo['locale']);
    } elseif (!empty($additionalAuthorInfo['afiliation'])) {
        // Adiciona apenas a afiliação se presente
        $seteZeroZero = 'a' . htmlspecialchars($additionalAuthorInfo['surname']) . ', ' . htmlspecialchars($additionalAuthorInfo['givenName']) . '7INT8' . htmlspecialchars($additionalAuthorInfo['afiliation']) . '9' . htmlspecialchars($additionalAuthorInfo['locale']);
    } else {
        // Adiciona sem ORCID e afiliação se nenhum estiver presente
        $seteZeroZero = 'a' . htmlspecialchars($additionalAuthorInfo['surname']) . ', ' . htmlspecialchars($additionalAuthorInfo['givenName']) . '$5(*)9' . htmlspecialchars($additionalAuthorInfo['locale']);
    }
}

    $oitoCincoMeiaA = '4 zClicar sobre o botão para acesso ao texto completo'.
    'u'.'https://doi.org/'.htmlspecialchars($doi).'3DOI';
    $oitoCincoMeiaB = '41z'.'Clicar sobre o botão para acesso ao texto completou'.
    htmlspecialchars($publicationUrl).'3E-Livro  ';

    $noveQuatroCinco = 'aPbMONOGRAFIA/LIVROc06j2023lNACIONAL';

    
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
    // Pegue somente o primeiro coautor
    if ($i === 0) {
        // Atualiza as posições e comprimentos para o primeiro coautor
        $rec700POS = sprintf('%05d', $rec500CAR + $rec500POS);
        $rec700CAR = sprintf('%04d', strlen($seteZeroZero) + 4);
        $rec700 .= '700' . $rec700CAR . $rec700POS;
    }

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



$rec856B = '856000000000';
$rec945 = '945000000000';


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
        // Inserir o número de caracteres no início do mrk
        $marcContent = $numeroDeCaracteresFormatado . 'nam 22000193a 4500 '. $marcContent;


        return $marcContent;
            }

   /**
     * fim ESTRUTURA mrk
    *
                            * */
        
    /**
     * @copydoc ImportExportPlugin::executeCLI
     */
    public function executeCLI($scriptName, &$args)
    {
        $opts = $this->parseOpts($args, ['no-embed', 'use-file-urls']);
        $command = array_shift($args);
        $marcFile = array_shift($args);
        $pressPath = array_shift($args);

        AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_PKP_SUBMISSION);
        $pressDao = DAORegistry::getDAO('PressDAO');
        $userDao = DAORegistry::getDAO('UserDAO');
        $press = $pressDao->getByPath($pressPath);

        if (!$press) {
            if ($pressPath != '') {
                echo __('plugins.importexport.common.cliError')."\n";
                echo __('plugins.importexport.common.error.unknownPress', ['pressPath' => $pressPath])."\n\n";
            }
            $this->usage($scriptName);

            return;
        }

        if ($marcFile && $this->isRelativePath($marcFile)) {
            $marcFile = PWD.'/'.$marcFile;
        }

        switch ($command) {
            case 'export':
                $outputDir = dirname($marcFile);
                if (!is_writable($outputDir) || (file_exists($marcFile) && !is_writable($marcFile))) {
                    echo __('plugins.importexport.common.cliError')."\n";
                    echo __('plugins.importexport.common.export.error.outputFileNotWritable', ['param' => $marcFile])."\n\n";
                    $this->usage($scriptName);

                    return;
                }

                if ($marcFile != '') {
                    switch (array_shift($args)) {
                        case 'monograph':
                        case 'monographs':
                            $selectedSubmissions = array_slice($args, 1);
                            $marcContent = $this->exportSubmissions($selectedSubmissions);
                            file_put_contents($marcFile, $marcContent);

                            return;
                    }
                }
                break;
        }
        $this->usage($scriptName);
    }

    /**
     * @copydoc ImportExportPlugin::usage
     */
    public function usage($scriptName)
    {
        fatalError('Not implemented.');
    }
}
