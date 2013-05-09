<?php

if ( !function_exists('sys_get_temp_dir')) {
	function sys_get_temp_dir() {
		if( $temp=getenv('TMP') )        return $temp;
		if( $temp=getenv('TEMP') )        return $temp;
		if( $temp=getenv('TMPDIR') )    return $temp;
		$temp=tempnam(__FILE__,'');
		if (file_exists($temp)) {
			unlink($temp);
			return dirname($temp);
		}
		return null;
	}
}
class FileParser {
	static function fcall($cmd,$includeErrors = false) {
		$descriptorspec = array(
		0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		2 => array("pipe", "w") // stderr is a file to write to
		);

		$cwd = '/tmp';

		$process = proc_open($cmd, $descriptorspec, $pipes, $cwd);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		proc_close($process);
		
		return $stdout.($includeErrors?$stderr:'');
	}
	static function rrmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (filetype($dir."/".$object) == "dir") self::rrmdir($dir."/".$object); else unlink($dir."/".$object);
				}
			}
			reset($objects);
			rmdir($dir);
		}
	}
	static function file_get_contents_utf8($fn) {
		$opts = array(
			'http' => array(
				'method'=>"GET",
				'header'=>"Content-Type: text/html; charset=utf-8"
		)
		);

		$context = stream_context_create($opts);
		$result = @file_get_contents($fn,false,$context);
		return $result;
	}
	static function GetStringContents($string,$ext) {
		$tmpfile = sys_get_temp_dir().'/file_parser_'.md5($string);
		file_put_contents($tmpfile,$string);
		$contents = self::GetFileContents($tmpfile,$ext);
		unlink($tmpfile);
		return $contents;
	}
	static function GetFileContents($fn,$ext = NULL) {
		if ($ext === null) $ext = pathinfo($fn,PATHINFO_EXTENSION);
		
		if (!file_exists($fn)) return FALSE;
		switch (strtolower($ext)) {
			case 'txt':
			case 'text':
				return self::file_get_contents_utf8($fn);
			case 'doc':
				return self::fcall('antiword "'.$fn.'"');
			case 'pdf':
				return self::fcall('pdftotext "'.$fn.'" -');
			case 'docx':
				// unzip it
				$tmpfld = sys_get_temp_dir().'/docx'.md5($fn);
				if (!file_exists($tmpfld)) mkdir($tmpfld);
				self::fcall('unzip "'.$fn.'" -d "'.$tmpfld.'"');

				if (!file_exists($tmpfld.'/word/document.xml')) return FALSE;
				$content = strip_tags(self::file_get_contents_utf8($tmpfld.'/word/document.xml'));

				self::rrmdir ($tmpfld);
				return $content;
				break;
		}
		return FALSE;
	}
}
