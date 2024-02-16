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
                    $exportFileName = $this->getExportPath() . '/omp.mrk';
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

  /**
     * ESTRUTURA mrk
                       *
                        * */

    //formando a data atual
    $currentDateTime = date('YmdHis.0');
    $marcContent .= "=005  {$currentDateTime}" . PHP_EOL;
    //que data é essa? bl = país?, 'por = idioma'
    $marcContent .= '=008  230919s2023\\\\\\\\bl\\\\\\\\\\\\\\\\\\\\\\\\000\0\por\d' . PHP_EOL;
    //isbn
    $cleanIsbn = preg_replace('/[^0-9]/', '', $isbn);
    $marcContent .= '=020  \\\$a' . htmlspecialchars($cleanIsbn) . PHP_EOL;
    //doi
    $marcContent .= '=024  7\$a' . htmlspecialchars($doi). '$2DOI' . PHP_EOL;
    //fonte catalogadora
    $marcContent .= '=040  \\\$aUSP/ABCD' . PHP_EOL;
    //idioma 'por'
    $marcContent .= '=041  0\$apor' . PHP_EOL;
    //país bl = brasil?
    $marcContent .= '=044  \\\$abl' . PHP_EOL;
    
    //primeira autora - Sobrenome, Nome - Orcid - Afiliação - País
    $firstAuthor = reset($authorsInfo);

    if (!empty($firstAuthor['orcid']) && !empty($firstAuthor['afiliation'])) {
        $marcContent .= '=100  1\$a' . htmlspecialchars($firstAuthor['surname']) . ', ' . htmlspecialchars($firstAuthor['givenName']) . '$0' . $firstAuthor['orcid'] . '$5(*)$7INT$8' . $firstAuthor['afiliation'] . '$9' . htmlspecialchars($firstAuthor['locale']) . PHP_EOL;
    } elseif (!empty($firstAuthor['orcid'])) {
        // Adiciona apenas o ORCID se presente
        $marcContent .= '=100  1\$a' . htmlspecialchars($firstAuthor['surname']) . ', ' . htmlspecialchars($firstAuthor['givenName']) . '$0' . $firstAuthor['orcid'] . '$5(*)$7INT$9' . htmlspecialchars($firstAuthor['locale']) . PHP_EOL;
    } elseif (!empty($firstAuthor['afiliation'])) {
        // Adiciona apenas a afiliação se presente
        $marcContent .= '=100  1\$a' . htmlspecialchars($firstAuthor['surname']) . ', ' . htmlspecialchars($firstAuthor['givenName']) . '$7INT$8' . $firstAuthor['afiliation'] . '$9' . htmlspecialchars($firstAuthor['locale']) . PHP_EOL;
    } else {
        // Adiciona sem ORCID e afiliação se nenhum estiver presente
        $marcContent .= '=100  1\$a' . htmlspecialchars($firstAuthor['surname']) . ', ' . htmlspecialchars($firstAuthor['givenName']) . '$5(*)$9' . htmlspecialchars($firstAuthor['locale']) . PHP_EOL;
    }
    

    //titulo
    $marcContent .= '=245  12$a'.htmlspecialchars($submissionTitle).'$h[recurso eletrônico]' . PHP_EOL;
    
    //Local - copyright - c/ano
    $marcContent .= '=260  \\\$aLOCAL'.'$b'.htmlspecialchars($copyright).'$c'.htmlspecialchars($copyrightyear). PHP_EOL;
    //XX = numero de páginas, p$ = página? bil = ?
    //omp não demonstra numero de páginas
    $marcContent .= '=300  \\\$aXX p$bil' . PHP_EOL;
    
    // Obter a data e hora atuais
    $currentDateTime = date('d.m.Y');
    //link e acesso - (deve ser o pdf) com data de acesso
    $marcContent .= '=500  \\\$aDisponível em: ' . htmlspecialchars($publicationUrl) . '. Acesso em: ' . $currentDateTime . PHP_EOL;
    
    // Sinopse
    //verificar se é necessário
    $cleanAbstract = str_replace(['<p>', '</p>'], '', $abstract);
    $marcContent .= '=520  \\\$a' . htmlspecialchars_decode($cleanAbstract) . PHP_EOL;
    
    // Demais autoras- Sobrenome, Nome - Orcid - Afiliação - País
    $additionalAuthors = array_slice($authors, 1); // Pular o primeiro autor
    foreach ($additionalAuthors as $additionalAuthor) {
    $additionalAuthorInfo = [
        'givenName' => $additionalAuthor->getLocalizedGivenName(),
        'surname' => $additionalAuthor->getLocalizedFamilyName(),
        'orcid' => $additionalAuthor->getOrcid(),
        'afiliation' => $additionalAuthor->getLocalizedAffiliation(),
        'locale' => $additionalAuthor->getCountryLocalized(),
    ];

    if (!empty($additionalAuthorInfo['orcid']) && !empty($additionalAuthorInfo['afiliation'])) {
        $marcContent .= '=700  1\$a' . htmlspecialchars($additionalAuthorInfo['surname']) . ', ' . htmlspecialchars($additionalAuthorInfo['givenName']) . '$0' . $additionalAuthorInfo['orcid'] . '$5(*)$7INT$8' . $additionalAuthorInfo['afiliation'] . '$9' . htmlspecialchars($additionalAuthorInfo['locale']) . PHP_EOL;
    } elseif (!empty($additionalAuthorInfo['orcid'])) {
        // Adiciona apenas o ORCID se presente
        $marcContent .= '=700  1\$a' . htmlspecialchars($additionalAuthorInfo['surname']) . ', ' . htmlspecialchars($additionalAuthorInfo['givenName']) . '$0' . $additionalAuthorInfo['orcid'] . '$5(*)$7INT$9' . htmlspecialchars($additionalAuthorInfo['locale']) . PHP_EOL;
    } elseif (!empty($additionalAuthorInfo['afiliation'])) {
        // Adiciona apenas a afiliação se presente
        $marcContent .= '=700  1\$a' . htmlspecialchars($additionalAuthorInfo['surname']) . ', ' . htmlspecialchars($additionalAuthorInfo['givenName']) . '$7INT$8' . $additionalAuthorInfo['afiliation'] . '$9' . htmlspecialchars($additionalAuthorInfo['locale']) . PHP_EOL;
    } else {
        // Adiciona sem ORCID e afiliação se nenhum estiver presente
        $marcContent .= '=700  1\$a' . htmlspecialchars($additionalAuthorInfo['surname']) . ', ' . htmlspecialchars($additionalAuthorInfo['givenName']) . '$5(*)$9' . htmlspecialchars($additionalAuthorInfo['locale']) . PHP_EOL;
    }
}
    
    //doi
    $marcContent .= '=856  4\$zClicar sobre o botão para acesso ao texto completo$uhttps://doi.org/'.htmlspecialchars($doi).'$3DOI' . PHP_EOL;
    //link -deve ser o pdf
    $marcContent .= '=856  41$zClicar sobre o botão para acesso ao texto completo$u'.htmlspecialchars($publicationUrl).'$3E-Livro' . PHP_EOL;
    //...
    $marcContent .= '=945  \\\$aP$bMONOGRAFIA/LIVRO$c06$j2023$lNACIONAL' . PHP_EOL;
    
    
}
        // Calcular o número de caracteres
        $numeroDeCaracteres = mb_strlen($marcContent, 'UTF-8'); 
        // Formatar o número de caracteres como uma string de 5 dígitos
        $numeroDeCaracteresFormatado = sprintf("%05d", $numeroDeCaracteres);
        // Inserir o número de caracteres no início do mrk
        $marcContent = '=LDR  ' . $numeroDeCaracteresFormatado . 'nam 2200349Ia 4500' . PHP_EOL . $marcContent;


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
