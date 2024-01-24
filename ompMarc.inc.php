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
                        'getParams' => new stdClass(),
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
                    $exportFileName = $this->getExportPath() . '/record.txt';
                    $fileManager->writeFile($exportFileName, $exportXml);
                    $fileManager->downloadByPath($exportFileName);
                    $fileManager->deleteByPath($exportFileName);
                    break;
//Parte responsávle pelo form

                        // no break
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
        return 'ompMarc222';
    }

    

  

    /**
     * FUNÇÃO PRINCIPAL, RESPOSÁVEL PELA ESTRUTURA DO ARQUIVO txt.
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
        $authorsInfo = [];
        $authors = $submission->getAuthors();

        foreach ($authors as $author) {
            $authorInfo = [
        'givenName' => $author->getLocalizedGivenName(),
        'surname' => $author->getLocalizedFamilyName(),
        'afiliation' => $author->getLocalizedAffiliation(),
        'orcid' => $author->getOrcid(),
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
            // aqui retorna ano mes dia $publicationYear = $submission->getDatePublished();
            $publicationDate = $submission->getDatePublished();
            $publicationYear = date('Y', strtotime($publicationDate));
            $publicationMonth = date('m', strtotime($publicationDate));
            $publicationDay = date('d', strtotime($publicationDate));
            //timestamp
            $timestamp = date('YmdHis').substr((string) microtime(), 2, 3);

            // aqui retorna xx_XX (pt-BR ou en_US etc) sendo o idioma em que a publicação foi submetida
            $submissionLanguage = substr($submission->getLocale(), 0, 2); //aqui retorna xx
            $publisherName = $press->getData('publisher');
            $registrant = $press->getLocalizedName();

            // Obtendo dados dos autores
            $authorNames = [];
            $authors = $submission->getAuthors();
            foreach ($authors as $author) {
                $givenName = $author->getLocalizedGivenName();
                $surname = $author->getLocalizedFamilyName();
                $afiliation = $author->getLocalizedAffiliation();
                $authorNames[] = $givenName.' '.$surname;
            }
            $authorName = implode(', ', $authorNames);
            $orcid = $author->getOrcid();

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

            /*
             *
             * ESTRUTURA XML/txt
             *
             * */

            //---início estrutura xml codigos obrigatórios
            //numero de caracteres
            $xmlContent = '01904 am a22002893u 4500' . ' 
             ';
         
            $xmlContent .= '001 usp000000468' . ' 
            ';
            //isbn
            $cleanIsbn = preg_replace('/[^0-9]/', '', $isbn);
            $xmlContent .= '020 $a ' . htmlspecialchars($cleanIsbn) . ' 
            ';

            //doi
            $xmlContent .= '024 7 $a '.htmlspecialchars($doi).' $2 doi' . ' 
            ';
            
            $xmlContent .= '042 $a dc' . ' 
            ';
            //primeira autora
            $firstAuthor = reset($authorsInfo);
            $xmlContent .= '100 10 $a '.htmlspecialchars($firstAuthor['surname']).', '.htmlspecialchars($firstAuthor['givenName']).', $e author' . ' 
            ';
            //titulo
            $xmlContent .= '245 10 $a ' .htmlspecialchars($submissionTitle).' 
            ';
            //portal EM INGLES e ano
            $xmlContent .= '260 $b USP Open Books Portal, $c '.htmlspecialchars($publicationYear).'.' . ' 
            ';
            
            $xmlContent .= '300 $a 1 online resource' . ' 
            ';
            //doi
            $xmlContent .= '500 $a '.htmlspecialchars($doi) . ' 
            ';
            //link do livro
            $xmlContent .= '500 $a '.htmlspecialchars($publicationUrl). ' 
            ';
            
            $xmlContent .= '506 0 $a Free-to-read $f Unrestricted online access $2 star' . ' 
            ';
            
            //sinopse
            $cleanAbstract = str_replace(['<p>', '</p>'], '', $abstract);
            $xmlContent .= '520 $a ' . htmlspecialchars_decode($cleanAbstract) . ' 
            ';

            
            $xmlContent .= '655 7 $a Livro $2 local' . ' 
            ';

            //demais autoras
            $additionalAuthors = array_slice($authors, 1); // Pular o primeiro autor
            foreach ($additionalAuthors as $additionalAuthor) {
                $additionalAuthorInfo = [
                    'givenName' => $additionalAuthor->getLocalizedGivenName(),
                    'surname' => $additionalAuthor->getLocalizedFamilyName(),
                ];
                $xmlContent .= '700 10 $a ' . htmlspecialchars($additionalAuthorInfo['surname']) . ', ' . htmlspecialchars($additionalAuthorInfo['givenName']) . ', $e author' . ' 
                ';
            }


            //portal
            $pressName = $press->getLocalizedName();
            $xmlContent .= '786 0 $n ' . $pressName . ';' . ' 
            ';
            //portal em ingles
            $xmlContent .= '786 0 $n USP Open Books Portal;' . ' 
            ';
            //portal
            $pressName = $press->getLocalizedName();
            $xmlContent .= '793 0 $a ' . $pressName . ' 
            ';
            
            //link do livro
            $xmlContent .= '856 40 $z Free-to-read: $u '.htmlspecialchars($publicationUrl).' $7 0' . ' 
            ';
            
            $xmlContent .= '949 $a Electronic resource $w ASIS $m ONLINE $k ONLINE $l ONLINE $o USP OA harvest 471 records 20210803 $r Y $s Y $t ONLINE' . ' 
            ';
            
            $xmlContent .= '997 $a BSLW: DO NOT PROCESS.' . ' 
            ';
            
            $xmlContent .= '596 $a 42' . ' 
            ';
            
            $xmlContent .= '926 $a ONLINE $b ONLINE $c Electronic resource $d ONLINE $f 1' . ' 
            ';
        }

        return $xmlContent;
    }

    /**
     * Final estrutura txt.
     */





     
    /**
     * @copydoc ImportExportPlugin::executeCLI
     */
    public function executeCLI($scriptName, &$args)
    {
        $opts = $this->parseOpts($args, ['no-embed', 'use-file-urls']);
        $command = array_shift($args);
        $xmlFile = array_shift($args);
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

        if ($xmlFile && $this->isRelativePath($xmlFile)) {
            $xmlFile = PWD.'/'.$xmlFile;
        }

        switch ($command) {
            case 'export':
                $outputDir = dirname($xmlFile);
                if (!is_writable($outputDir) || (file_exists($xmlFile) && !is_writable($xmlFile))) {
                    echo __('plugins.importexport.common.cliError')."\n";
                    echo __('plugins.importexport.common.export.error.outputFileNotWritable', ['param' => $xmlFile])."\n\n";
                    $this->usage($scriptName);

                    return;
                }

                if ($xmlFile != '') {
                    switch (array_shift($args)) {
                        case 'monograph':
                        case 'monographs':
                            $selectedSubmissions = array_slice($args, 1);
                            $xmlContent = $this->exportSubmissions($selectedSubmissions);
                            file_put_contents($xmlFile, $xmlContent);

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
