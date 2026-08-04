<?php
class SFTPConnection{
    private $connection;
    private $sftp;

    public function __construct($host, $port = 22){
		$this->connection = @ssh2_connect($host, $port);
		$this->TsTriggers = new TsTriggers();
		if (! $this->connection){
			$this->TsTriggers->SetError(["Could not connect to $host on port $port."]);
			$this->TsTriggers->SendTriggerErrors();
			throw new Exception("Could not connect to $host on port $port.");
		}
    }

    public function login($username, $password)
    {
        if (! @ssh2_auth_password($this->connection, $username, $password)){
			
			$this->TsTriggers->SetError(["Could not authenticate with username $username " . "and password $password."]);
			$this->TsTriggers->SendTriggerErrors();
			
            throw new Exception("Could not authenticate with username $username " . "and password $password.");
        }
		$this->sftp = @ssh2_sftp($this->connection);
        if (! $this->sftp){
			$this->TsTriggers->SetError(["Could not initialize SFTP subsystem."]);
			$this->TsTriggers->SendTriggerErrors();
            throw new Exception("Could not initialize SFTP subsystem.");
		}
    }

    public function uploadFile($local_file, $remote_file)
    {
        $sftp = $this->sftp;
        $stream = @fopen("ssh2.sftp://$sftp$remote_file", 'w');
        if (! $stream){
			$this->TsTriggers->SetError(["Could not open file: $remote_file"]);
			$this->TsTriggers->SendTriggerErrors();
            throw new Exception("Could not open file: $remote_file");
        }
		$data_to_send = @file_get_contents($local_file);
        if ($data_to_send === false){
			$this->TsTriggers->SetError(["Could not open local file: $local_file."]);
			$this->TsTriggers->SendTriggerErrors();
            throw new Exception("Could not open local file: $local_file.");
        }
		if (@fwrite($stream, $data_to_send) === false){
			$this->TsTriggers->SetError(["Could not send data from file: $local_file."]);
			$this->TsTriggers->SendTriggerErrors();
            throw new Exception("Could not send data from file: $local_file.");
        }
		@fclose($stream);
    }
   
	function scanFilesystem($remote_file) {
		$sftp = $this->sftp;
		$dir = "ssh2.sftp://$sftp$remote_file"; 
		$tempArray = array();
		$handle = opendir($dir);
		// List all the files
		while (false !== ($file = readdir($handle))) {
			if (substr("$file", 0, 1) != "."){
				if(is_dir($file)){
		//                $tempArray[$file] = $this->scanFilesystem("$dir/$file");
				} else {
					$filemtime = filemtime($dir . $file);
					$tempArray[$filemtime]=$file;
				}
			}
		}
		closedir($handle);
		return $tempArray;
	}   

    public function receiveFile($remote_file, $local_file)
    {
        $sftp = $this->sftp;
		
		ssh2_scp_recv($this->connection, $remote_file, $local_file);
		/*
        $stream = @fopen("ssh2.sftp://$sftp$remote_file", 'r');
        if (! $stream)
            throw new Exception("Could not open file: $remote_file");
        $contents = fread($stream, filesize("ssh2.sftp://$sftp$remote_file"));           
        file_put_contents ($local_file, $contents);
        @fclose($stream);*/
    }
       
    public function deleteFile($remote_file){
      $sftp = $this->sftp;
      unlink("ssh2.sftp://$sftp$remote_file");
    }
}