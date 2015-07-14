<?php namespace Jakjr\Deployer;

use Exception;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class DeployerCommand extends Command {

    private $newTag;
    private $user;
    private $pass;
    private $trunkUrl;
    private $newTagUrl;

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'jakjr:deployer';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Realiza o deploy (tagging & deploy) de projetos.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
        try {
            $this->getCredentials();

            if ($this->option('incremental')) {
                $this->newTag = $this->getIncrementalTag();
                if ( !$this->confirm("Será criada a tag {$this->newTag}, ok? [yes|no]", true) ) {
                    $this->info('Comando abortado.');
                    return;
                }
            } else {
                $this->newTag = $this->argument('tag');
            }

            if (is_null($this->newTag)) {
                $this->showLastTag();
                return;
            }

            $this->makeUrls();
            $this->checkTag();
            $this->updateLightTagFile();
            $this->commitLightTagFile();
            $this->makeSvnCopy();
            $this->makeBuild();
            $this->info('Fim. by João Alfredo');
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return;
        }
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('tag', InputArgument::OPTIONAL, 'Tag específica para o deploy.'),
		);
	}

    protected function getOptions()
    {
        return array(
            array('incremental', 'i', InputOption::VALUE_NONE, 'Realiza o deploy incrementando automaticamente a última tag + 1.'),
        );
    }



    private function getCredentials()
    {
        if ( (!isset($_ENV['DEPLOY_USER'])) || (!isset($_ENV['DEPLOY_PASS'])) ) {
            throw new Exception('Variável de ambiente DEPLOY* não configurada em .env.local.php');
        }
        $this->user = $_ENV['DEPLOY_USER'];
        $this->pass = $_ENV['DEPLOY_PASS'];
    }

    private function makeUrls()
    {
        $svnInfoCommand = "svn info --xml";
        $info = simplexml_load_string(@shell_exec($svnInfoCommand));
        $this->trunkUrl = (string)$info->entry->url;
        $this->newTagUrl = str_replace('/trunk', "/tags/{$this->newTag}", $this->trunkUrl);
    }

    private function checkTag()
    {
        $svnLsCommand = "svn --username={$this->user} --password={$this->pass} --no-auth-cache ls {$this->newTagUrl} 2>>/dev/null";
        @exec($svnLsCommand, $return, $returnVar);
        if ($returnVar == 0) { //o ls foi bem sucedido, logo existe o newTagUrl
            throw new Exception("Tag {$this->newTag} já existe.");
        }
    }

    private function updateLightTagFile()
    {
        $file = app_path() . '/config/packages/celepar/light/config.php';

        if (! File::exists($file) ) {
            throw new Exception("Arquivo não existe: $file");
        }

        $content = File::get($file);
        $newContent = preg_replace("/appVersion(.*)/", "appVersion' => '{$this->newTag}',", $content);
        if ( File::put($file, $newContent) == false) {
            throw new Exception('Problemas atualizando o arquivo: ' . $file);
        }
        $this->info('config.php atualizado');
    }

    private function commitLightTagFile()
    {
        $file = app_path() . '/config/packages/celepar/light/config.php';
        $svnCommitCommand = "svn --username={$this->user} --password={$this->pass} --no-auth-cache ci -m 'Versionamento' $file 2>&1";
        @exec($svnCommitCommand, $return, $returnVar);
        if ($returnVar) {
            throw new Exception($return[0]);
        }
        $this->info('config.php commitado.');
    }

    private function makeSvnCopy()
    {
        $svnCopyCommand = "svn --username={$this->user} --password={$this->pass} --no-auth-cache copy -q -m '' {$this->trunkUrl} {$this->newTagUrl}";
        @exec($svnCopyCommand, $return, $returnVar);
        if ($returnVar) {
            throw new Exception($return[0]);
        }
        $this->info('Criado copia no SVN do projeto.');
    }

    private function makeBuild()
    {
        $jenkinsBuildCommand = "curl -s -X POST --user '{$this->user}:{$this->pass}' 'http://jenkins-ci.celepar.parana/job/ppd-tag/buildWithParameters?TAG={$this->newTag}'";
        @exec($jenkinsBuildCommand, $return, $returnVar);
        if ($returnVar) {
            throw new Exception($return[0]);
        }
        $this->info('Build no Jenkins iniciado');
    }

    private function getLastTag()
    {
        $svnInfoCommand = "svn info --xml";
        $info = simplexml_load_string(@shell_exec($svnInfoCommand));
        $this->trunkUrl = (string)$info->entry->url;
        $tagUrl = str_replace('/trunk', "/tags", $this->trunkUrl);

        $svnLastTagCommand = "svn --username={$this->user} --password={$this->pass} --no-auth-cache log -v $tagUrl --limit 1 | awk '/^   A/ { print $2 }' | grep -v RC |  head -1";

        @exec($svnLastTagCommand, $return, $returnValue);
        if ($returnValue) {
            throw new Exception('Algo deu errado');
        }

        $lastTag = str_replace('/tags/', '', $return[0]);
        return $lastTag;
    }

    private function showLastTag()
    {
        $this->info($this->getLastTag());
    }

    private function getIncrementalTag()
    {
        $lastTag = $this->getLastTag();
        $tagParts = explode('.', $lastTag);
        $tagParts[2] = $tagParts[2] + 1;
        return implode('.', $tagParts);
    }

}
