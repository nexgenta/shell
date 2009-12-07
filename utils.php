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

class ShellLs extends CommandLine
{
	protected $onePerLine;
	protected $allExceptDotDirs;
	protected $all;
	protected $escapeUnprint;
	protected $CescapeUnprint;
	protected $forceColumns;
	protected $useColour;
	protected $humanUnits;
	protected $traverseLinks;
	protected $long = true;
	protected $year;
	protected $typeIndicators = true;
	
	protected function checkargs(&$args)
	{
		if(!parent::checkargs($args)) return false;
		if(!count($args)) $args = array('.');
		return true;
	}
	
	public function main($args)
	{
		$this->year = strftime('%Y');
		$err = 0;
		foreach($args as $path)
		{
			if(!($this->listPath($path))) $err++;
		}
		return $err ? 1 : 0;
	}
	
	protected function uid($uid)
	{
		static $uidcache = array();
		if(!isset($uidcache[$uid]))
		{
			if(($n = posix_getpwuid($uid)))
			{
				$uidcache[$uid] = $n['name'];
			}
			else
			{
				$uidcache[$uid] = $uid;
			}
		}
		return $uidcache[$uid];
	}

	protected function gid($gid)
	{
		static $gidcache = array();
		if(!isset($gidcache[$gid]))
		{
			if(($n = posix_getgrgid($gid)))
			{
				$gidcache[$gid] = $n['name'];
			}
			else
			{
				$gidcache[$gid] = $uid;
			}
		}
		return $gidcache[$gid];
	}
	
	protected function time($stamp)
	{
		if(strftime('%Y', $stamp) == $this->year)
		{
			return strftime('%b %e %H:%M', $stamp);
		}
		return strftime('%b %e  %Y', $stamp);
	}
	
	protected function mode($mode)
	{
		switch($mode & 0170000)
		{
			case 0140000: $str = 's'; break;
			case 0120000: $str = 'l'; break;
			case 0060000: $str = 'b'; break;
			case 0040000: $str = 'd'; break;
			case 0020000: $str = 'c'; break;
			case 0010000: $str = 'p'; break;
			default:
				$str = '-';
		}
		$str .= (($mode&0x0100) ? 'r' : '-' ) . (($mode&0x0080) ? 'w':'-');
		$str .= (($mode&0x0040) ? (($mode&0x0800) ?'s':'x'):(($mode&0x0800)?'S':'-'));
		$str .= (($mode&0x0020) ?'r':'-').(($mode&0x0010)?'w':'-');
		$str .= (($mode&0x0008) ? (($mode&0x0400) ?'s':'x'):(($mode&0x0400)?'S':'-'));
		$str .= (($mode&0x0004) ? 'r':'-').(($mode&0x0002)?'w':'-');
		$str .= (($mode&0x0001) ? (($mode&0x0200) ?'t':'x'):(($mode&0x0200)?'T':'-'));
		$str .= ' ';
		return $str;
	}
	
	protected function listPath($path)
	{
		if(!($d = opendir($path)))
		{
			return false;
		}
		$entries = array();
		$maxuid = 0;
		$maxgid = 0;
		$maxlinks = 0;
		$maxsize = 0;
		$maxname = 0;
		while(($entry = readdir($d)))
		{
			if(!$this->allExceptDotDirs && !$this->all && $entry[0] == '.') continue;
			if(!$this->all && (!strcmp($entry, '.') || !strcmp($entry, '..'))) continue;
			if(!($stat = lstat($path . '/' . $entry))) continue;
			$mode = intval($stat['mode'], 8);
			$name = $entry;
			if($this->typeIndicators)
			{
				if(($mode & 0040000) == 0040000)
				{
					$name .= '/';
				}
				else if(($mode & 0120000) == 0120000)
				{
					$name .= '@';
				}
				else if(($mode & 0140000) == 0140000)
				{
					$name .= '=';
				}
			}
			if($this->long)
			{
				$e = array(
					'mode' => $this->mode($mode),
					'name' => $name,
					'uid' => $this->uid($stat['uid']),
					'gid' => $this->gid($stat['gid']),
					'nlink' => $stat['nlink'],
					'size' => $stat['size'],
					'mtime' => $this->time($stat['mtime']),
					'suffix' => null,
				);
				if(($mode & 0120000) == 0120000)
				{
					$e['suffix'] = ' -> ' . readlink($path . '/' . $entry);
				}
				if(($l = strlen($e['uid'])) > $maxuid) $maxuid = $l;
				if(($l = strlen($e['gid'])) > $maxgid) $maxgid = $l;
				if(($l = strlen($e['nlink'])) > $maxlinks) $maxlinks = $l;
				if(($l = strlen($e['size'])) > $maxsize) $maxsize = $l;
				if(($l = strlen($e['name'])) > $maxname) $maxname = $l;
				$entries[] = $e;
			}
			else if($this->forceColumns)
			{
				$e = array('name' => $name);
				if(($l = strlen($e['name'])) > $maxname) $maxname = $l;
				$entries[] = $e;
			}
			else
			{
				echo $entry . "\n";
			}
		}
		if($this->long)
		{
			$format = '%s %-' . $maxlinks . 's %-' . $maxuid . 's %-' . $maxgid . 's %' . $maxsize . 's %s %s%s' . "\n";
			foreach($entries as $entry)
			{
				echo sprintf($format, $entry['mode'], $entry['nlink'], $entry['uid'], $entry['gid'], $entry['size'], $entry['mtime'], $entry['name'], $entry['suffix']);
			}
		}
		closedir($d);
		return true;
	}
}

class ShellChdir extends CommandLine
{
	protected function checkargs(&$args)
	{
		if(!(parent::checkargs($args))) return false;
		if(!count($args))
		{
			$args = array(getenv('HOME'));
		}
		return true;
	}
	
	public function main($args)
	{
		return chdir($args[0]) ? 0 : 1;
	}
}

class ShellMkdir extends CommandLine
{
	protected function checkargs(&$args)
	{
		if(!(parent::checkargs($args))) return false;
		if(!count($args))
		{
			return $this->error(Error::NO_OBJECT_SPECIFIED, null, null, 'Usage: mkdir [OPTIONS] PATH [PATH...]');
		}
		return true;
	}
	
	public function main($args)
	{
		$err = 0;
		foreach($args as $path)
		{
			if(!mkdir($path)) $err++;
		}
		return $err == 0 ? 0 : 1;
	}
}
