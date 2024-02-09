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
                    $exportFileName = $this->getExportPath() . '/omp.mrk';
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
        $authorsInfo = [];
        $authors = $submission->getAuthors();

        foreach ($authors as $author) {
            $authorInfo = [
        'givenName' => $author->getLocalizedGivenName(),
        'surname' => $author->getLocalizedFamilyName(),
        //'afiliation' => $author->getLocalizedAffiliation(),
        
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
           // $copyright = $submission->getLocalizedcopyrightHolder();
            // aqui retorna ano mes dia $publicationYear = $submission->getDatePublished();
            $publicationDate = $submission->getDatePublished();
            $publicationYear = date('Y', strtotime($publicationDate));
            $publicationMonth = date('m', strtotime($publicationDate));
            $publicationDay = date('d', strtotime($publicationDate));
            //timestamp

            $publisherName = $press->getData('publisher');
            $registrant = $press->getLocalizedName();

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

    $xmlContent .= '=001  usp000000468' . PHP_EOL;
    // ISBN
    $cleanIsbn = preg_replace('/[^0-9]/', '', $isbn);
    $xmlContent .= '=020  \\\$a' . htmlspecialchars($cleanIsbn) . PHP_EOL;

    // DOI
    $xmlContent .= '=024  7\$' . htmlspecialchars($doi) . '$2doi' . PHP_EOL;

    $xmlContent .= '=042  \\\$adc' . PHP_EOL;

    // Primeira autora
    $firstAuthor = reset($authorsInfo);
    $xmlContent .= '=100  10$a' . htmlspecialchars($firstAuthor['surname']) . ', ' . htmlspecialchars($firstAuthor['givenName']) . ',$eauthor' . PHP_EOL;

    // Título
    $xmlContent .= '=245  10$a' . htmlspecialchars($submissionTitle) . PHP_EOL;

    // Portal em inglês e ano
    $xmlContent .= '=260  \\\$bUSP Open Books Portal, $c' . htmlspecialchars($publicationYear) . '.' . PHP_EOL;

    $xmlContent .= '=300  \\\$a1 online resource' . PHP_EOL;

    // DOI
    $xmlContent .= '=500  \\\$a' . htmlspecialchars($doi) . PHP_EOL;

    // Link do livro
    $xmlContent .= '=500  \\\$a' . htmlspecialchars($publicationUrl) . PHP_EOL;

    $xmlContent .= '=506  0\$aFree-to-read$fUnrestricted online access$2star' . PHP_EOL;

    // Sinopse
    $cleanAbstract = str_replace(['<p>', '</p>'], '', $abstract);
    $xmlContent .= '=520  \\\$a' . htmlspecialchars_decode($cleanAbstract) . PHP_EOL;

    $xmlContent .= '=655  7\$aLivro$2local' . PHP_EOL;

    // Demais autoras
$additionalAuthors = array_slice($authors, 1); // Pular o primeiro autor
foreach ($additionalAuthors as $additionalAuthor) {
    $additionalAuthorInfo = [
        'givenName' => $additionalAuthor->getLocalizedGivenName(),
        'surname' => $additionalAuthor->getLocalizedFamilyName(),
    ];
    $xmlContent .= '=700  10$a' . htmlspecialchars($additionalAuthorInfo['surname']) . ', ' . htmlspecialchars($additionalAuthorInfo['givenName']) . ',$eauthor' . PHP_EOL;
}

    // Portal
    $pressName = $press->getLocalizedName();
    $xmlContent .= '=786  0\$n' . $pressName . ';' . PHP_EOL;

    // Portal em inglês
    $xmlContent .= '=786  0\$nUSP Open Books Portal;' . PHP_EOL;

    // Portal
    $pressName = $press->getLocalizedName();
    $xmlContent .= '=793  0\$a' . $pressName .'.'. PHP_EOL;

    // Link do livro
    $xmlContent .= '=856  40$zFree-to-read:$u' . htmlspecialchars($publicationUrl) . '$70' . PHP_EOL;

    // hardcoding
    $xmlContent .= '=949  \\\$aElectronic resource$wASIS$mONLINE$kONLINE$lONLINE$oUSP OA harvest 471 records 20210803$rY$sY$tONLINE' . PHP_EOL;
    $xmlContent .= '=997  \\\$aBSLW: DO NOT PROCESS.' . PHP_EOL;
    $xmlContent .= '=596  \\\$a42' . PHP_EOL;
    $xmlContent .= '=926  \\\$aONLINE$bONLINE$cElectronic resource$dONLINE$f1' . PHP_EOL;
}

       // Calcular o número de caracteres
$numeroDeCaracteres = mb_strlen($xmlContent, 'UTF-8'); 

// Formatar o número de caracteres como uma string de 5 dígitos
$numeroDeCaracteresFormatado = sprintf("%05d", $numeroDeCaracteres);

// Inserir o número de caracteres no início do mrk
$xmlContent = '=LDR  ' . $numeroDeCaracteresFormatado . ' am a22002893u 4500' . PHP_EOL . $xmlContent;

return $xmlContent;
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
