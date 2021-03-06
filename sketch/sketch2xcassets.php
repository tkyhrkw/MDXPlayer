<?php

// sketch file to xcassets (use sketchtool)
// install tool  /Applications/Sketch.app/Contents/Resources/sketchtool/install.sh

$skarg = '--format=png --scales="1.0,2.0,3.0"';
$force = false;

$sketch = null;
$xcassets = null;
$gitignore = false;
$swift = null;

foreach ($argv as $a){
	if($a[0] == '-'){
		switch (strtolower(substr($a, 1))) {
			case 'pdf': $skarg = '--format=pdf'; break;
			case 'png': $skarg = '--format=png --scales="1.0,2.0,3.0"'; break;
			case 'jpg': $skarg = '--format=jpg --scales="1.0,2.0,3.0"'; break;
			case 'force': $force = true; break;
			case 'gitignore': $gitignore = true; break;
		}
	continue;
	}

	$pi = pathinfo($a);
	if(isset($pi['extension'])){
  		$ext = $pi['extension'];
		if($ext == 'sketch') $sketch = $a;
		if($ext == 'xcassets') $xcassets = $a;
		if($ext == 'swift') $swift = $a;
	}
}

if(!$sketch || !$xcassets || !file_exists($sketch)){
  print " php sketch2assets [opt] xxx.sketch xxx.xcassets [xxx.swift]
   opt
	-PDF output as PDF (default)
	-PNG output as PNG
	-JPG output as JPG 
	-force force write xcaseets
	\n";

  exit(0);
}

if(!$force && file_exists($xcassets)){
	if(filemtime($xcassets) > filemtime($sketch) && file_exists("$xcassets/Contents.json")){
		print "xcassets is new then sketch file. no execute.\n";
		exit(0);
	}
}

delTree($xcassets);
@mkdir($xcassets);

$cmd = "sketchtool export slices $sketch --output=$xcassets $skarg";
echo "$cmd\n";
echo exec($cmd);


$contnts = ['info' => ['version' => 1, 'author' => 'xcode']];
file_put_contents("$xcassets/Contents.json", json_encode($contnts, JSON_PRETTY_PRINT));
if($gitignore) file_put_contents("$xcassets/.gitignore", "*\n!.gitignore\n");


$files = array_diff(scandir($xcassets), array('.','..')); 
$list = [];

foreach($files as $file){
	if(strpos($file,'@') !== false) continue;

	$pi = pathinfo("$xcassets/$file");
	if(!isset($pi['extension'])) continue;

	$ext = $pi['extension'];
	$filename = $pi['filename'];
	if(!in_array($ext, ['pdf','png','jpg'])) continue;

	print "convert $file\n";
	$imageset = "$xcassets/$filename.imageset";
	mkdir($imageset);

	$contnts = ['info' => ['version' => 1, 'author' => 'xcode'],
		'images' => [['idiom' => 'universal', 'filename' => $file]]];

	if($ext != 'pdf') $contnts['images'][0]['scale'] = '1x';

	rename("$xcassets/$file", "$imageset/$file");

	$sfile = "$filename@2x.$ext";
	if(file_exists("$xcassets/$sfile")){
		$contnts['images'][] = ['idiom' => 'universal', 'filename' => $sfile, 'scale' => '2x'];
		rename("$xcassets/$sfile", "$imageset/$sfile");
	}

	$sfile = "$filename@3x.$ext";
	if(file_exists("$xcassets/$sfile")){
		$contnts['images'][] = ['idiom' => 'universal', 'filename' => $sfile, 'scale' => '3x'];
		rename("$xcassets/$sfile", "$imageset/$sfile");
	}

	file_put_contents("$imageset/Contents.json", json_encode($contnts, JSON_PRETTY_PRINT));
	$list[] = $filename;
}

if($swift){
	$pi = pathinfo($swift);
	$a = "// generated by sketch2assets\n// from $sketch\n// to $xcassets\n\n";
	$a .= "import UIKit\n\n";
	// $a .= "extension String {\n";
	// $a .= "	public var image: UIImage { return UIImage(named: self) ?? UIImage() }\n";
	// $a .= "}\n\n";

	$a .= "struct ${pi['filename']} {\n";
	foreach($list as $v) $a .= "\tstatic let $v: String = \"$v\"\n";
	$a .= "}\n";
	file_put_contents($swift, $a);
}


function delTree($dir) { 
   $files = array_diff(scandir($dir), array('.','..')); 
    foreach ($files as $file) { 
      (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file"); 
    } 
    return rmdir($dir); 
  } 

