<?php

/* Eregansu: A simple command-line shell
 *
 * Copyright 2009 Mo McRoberts.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The names of the author(s) of this software may not be used to endorse
 *    or promote products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, 
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY 
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL
 * AUTHORS OF THIS SOFTWARE BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF 
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING 
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS 
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

class Shell extends CommandLine
{
	protected $useReadline;
	protected $stdin;
	protected $cluster;
	
	public function main($args)
	{
		echo "Eregansu Command-line Shell\n";
		if(function_exists('readline'))
		{
			echo "- Readline is available\n";
			$this->useReadline = true;
		}
		if(defined('CLUSTER_IRI'))
		{
			require_once(APPS_ROOT . 'cluster/model.php');
			$this->cluster = ClusterModel::getInstance();
		}
		$this->stdin = fopen('php://stdin', 'r');
		$router = new ShellRouter;
		while(!$this->exit)
		{
			$cmdline = $this->getline();
			$cmd = $this->parseline($cmdline);
			if(!count($cmd) || !strlen($cmd[0]))
			{
				continue;
			}
			if(!strcmp($cmd[0], 'exit'))
			{
				break;
			}
			Error::$throw = true;		
			$req = Request::requestForSAPI('cli');
			$req->params = $cmd;
			try
			{
				try
				{
					$router->process($req);
				}
				catch(TerminalErrorException $ex)
				{
				}
			}
			catch(Exception $e)
			{
				echo $e . "\n";
			}
			$req = null;
		}
	}
	
	protected function prompt()
	{
		$cwd = getcwd();
		$lcwd = basename($cwd);
		if($this->cluster)
		{
			$str = '[' . $this->cluster->clusterName . '/' . $this->cluster->instanceName . ' ' . $lcwd . ']$ ';
		}
		else
		{
			$str = '[' . php_uname('n') . ' ' . $lcwd . ']$ ';
		}		
		return sprintf("\033[0m\033[97m%s\033[0m", $str);
	}
	
	protected function getline()
	{
		$prompt = $this->prompt();
		if($this->useReadline)
		{
			$cmdline = trim(readline($prompt));
			if(strlen($cmdline))
			{
				readline_add_history($cmdline);
			}
			return $cmdline;
		}
		echo $prompt;
		flush();
		$cmdline = trim(fgets($this->stdin));
		return $cmdline;
	}

	protected function parseline($line)
	{
		return explode(' ', $line);
	}
}

class ShellRouter extends DefaultApp
{
	public function __construct()
	{
		parent::__construct();
		$shelltools = array(
			'ls' => array('class' => 'ShellLs', 'name' => 'shell', 'file' => 'utils.php', 'description' => 'List files'),
			'cd' => array('class' => 'ShellChdir', 'name' => 'shell', 'file' => 'utils.php', 'description' => 'Change the current working directory'),
			'pwd' => array('class' => 'ShellPwd', 'name' => 'shell', 'file' => 'utils.php', 'description' => 'Show the current working directory'),
			'mkdir' => array('class' => 'ShellMkdir', 'name' => 'shell', 'file' => 'utils.php', 'description' => 'Create a directory'),
			'stat' => array('class' => 'ShellStat', 'name' => 'shell', 'file' => 'utils.php', 'description' => 'Display filesystem metadata associated with a file'),
			'cat' => array('class' => 'ShellCat', 'name' => 'shell', 'file' => 'utils.php', 'description' => 'Concatenate one or more input files into a single output file'),
			'cp' => array('class' => 'ShellCp', 'name' => 'shell', 'file' => 'utils.php', 'description' => 'Copy files from one location to another'),
		);
		$this->sapi['cli'] = array_merge($shelltools, $this->sapi['cli']);
	}
}
