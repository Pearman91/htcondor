#!/s/std/bin/perl

### need to do CRL stuff
### set SSLEAY_CONFIG to something else like condor_ssleay.cnf


###### INITIAL SETUP ####################################################
###parse command line, get rid of ARGV because it screws up reading from STDIN
### this isn't currently necessary until I fix reading from stdin for signing
@HOLDARGS = @ARGV;
@ARGV = ();
$USERDIR = $HOLDARGS[$#HOLDARGS]; #directory or maybe signcert output file
$_ = $HOLDARGS[0];

#ensure that ssl is installed, so we can keep a copy of CA's cert there
$SSLEAY=`which ssleay|sed 's/ssleay//'`;
chomp( $SSLEAY );
$SSLEAYLIB="$SSLEAY/../lib/";
if ( ! -r "$SSLEAYLIB/ssleay.cnf" ) {
	print "SSL-eay doesn't seem to be installed as expected and in your PATH\n";
	exit $!;
}

$DAYS="-days 365";
$SSLEAY_CONFIG="";
$RAND="$SSLEAYLIB/rand.dat";

###### END INITIAL SETUP ################################################



if ( /^-createca/i || /^-user/i || /^-daemon/i ) {
	if ( $#HOLDARGS != 1 ) {
		$_ = "-help";
	}
}
#this needs to be cleaned up, it's a mess, fix reading from stdin
#which doesn't work because ssl pukes on <> read, might just have
#to (correctly) reopen STDIN?
elsif ( ( /^-signcert/i ) && ( $#HOLDARGS == 3 
#|| $#HOLDARGS == 1  
			) ) 
{
#	if ( $#HOLDARGS == 1 ) {  #setup to read from STDIN
#		if ( ! -z STDIN  ) { # there actually is input in STDIN
#		   $INPUT_FILE="/tmp/tmp_$$.pem";
#			$CLEANUP = "unlink $INPUT_FILE";
#		   open INPUT_REQUEST, ">$INPUT_FILE";
#		   print INPUT_REQUEST <>;
#		   close INPUT_REQUEST;
#			open STDIN, "<";
#		}
#		else {
#			print "error: no input to read from STDIN\n";
#			exit 1;
#		}
#	}
#	else { #next option should be -infile
		$_ = $HOLDARGS[1];
		if ( ! /-infile/i ) { 
			$_ = "-help";
		}
		else {
			$INPUT_FILE = $HOLDARGS[2];
			if ( -r $INPUT_FILE ) {
				$_ = "-signcert";
			}
			else { 
				print "error: unable to read -infile \"$INPUT_FILE\"\n";
				exit 1;
			}
		}
#	}
}
else {
	$_ = "-help";
}

if ( /^-signcert/i || /^-user/i || /^-daemon/i ) {
	if ( ! -f "$SSLEAYLIB/cacert.pem" ) {
		print "error: to continue, a copy of the CA certificate must be "
			. "readable as \"$SSLEAYLIB/cacert.pem\"\n";
		exit 1;
	}
}

if ( /^-user/i || /^-daemon/i ) {
	if ( /^-daemon/i ) {
		#we don't want daemon certificates to be pass-phrase protected!
#		$PARAM = "-nodes";

		print "doing -daemon\n";
	}
	else {
		$PARAM = "-des3 1024";
		print "doing -user\n";
	}

	#create a certificate request
	print "creating certificate directory \"$USERDIR\"\n";
	if ( ! mkdir $USERDIR, 0755 ) {
		print "failure creating directory \"$USERDIR\", $!\n";
		exit $!;
	}
	if ( ! mkdir "$USERDIR/private", 0700 ) {
		print "failure creating directory \"$USERDIR/private\", $!\n";
		print "removing newly created directory \"$USERDIR\"\n";
		rmdir $USERDIR;
		exit $!;
	}
$USERKEY = "$USERDIR/private/newkey.pem";
system( "ssleay genrsa -rand $RAND $PARAM > $USERKEY" );
print "DEBUG A\n";
if ( system("ssleay req -new -key $USERKEY -out $USERDIR/newreq.pem $DAYS") ) {
print "DEBUG Z\n";
		print "error generating certificate request and private key\n";
		print "interactively removing newly created directory tree \"$USERDIR\"\n";
		system( "rm -ir $USERDIR" );
		exit 1;
	}
print "DEBUG B\n";
	chmod 0600, "$USERDIR/private/newkey.pem";
print "DEBUG 1, $SSLEAYLIB\n";
	$SYMLINK = `c_hash $SSLEAYLIB/cacert.pem|sed 's/ .*//'`;
print "DEBUG 2\n";
	chomp( $SYMLINK );
	if ( ! symlink( "$SSLEAYLIB/cacert.pem", "$USERDIR/$SYMLINK" ) ) {
		print "error creating symlink to CA certificate, $!\n";
		print "removing newly created directory tree\"$USERDIR\"\n";
		system( "rm -ir $USERDIR" );
		exit $!;
	}
	print "Certificate request is in \"$USERDIR/newreq.pem\",\n";
	print "Private key is in \"$USERDIR/private/newkey.pem\"\n";
	print "\n";
	print "Remember to have your request signed by your condor administrator\n";
	print "and store the signed certificate as \"$USERDIR/newcert.pem\"\n";
}
elsif ( /^-signcert/i ) {
	print "doing -signcert\n";

	if (system("ssleay ca $SSLEAY_CONFIG -in $INPUT_FILE -out $USERDIR")){
		print "error signing certificate, no output written to \"$USERDIR\"\n";
		eval $CLEANUP;
		exit 1;
	}
	system( "cat $USERDIR" );
	print "Signed certificate is in $USERDIR\n";
}
elsif ( /^-createca/i ) {
	print "doing -createca\n";

	print "generating random state to $RAND\n";
	system( "ps -Aaf>$RAND" );

	print "creating certificate authority (CA) directory \"$USERDIR\"\n";
	if ( ! mkdir $USERDIR, 0744 ) {
		print "failure creating directory \"$USERDIR\", $!\n";
		exit $!;
	}
	# create the directory hierarchy
	mkdir "$USERDIR/certs", 0700;
	mkdir "$USERDIR/crl", 0700;
	mkdir "$USERDIR/newcerts", 0700;
	mkdir "$USERDIR/private", 0700;
	system( "echo \"01\" > $USERDIR/serial" );
	system( "touch $USERDIR/index.txt" );
	print "Making CA certificate and key...\n";
#	system( "ssleay req -new -x509 -keyout $USERDIR/private/cakey.pem -out $USERDIR/cacert.pem $DAYS" );
system("ssleay genrsa -rand $RAND -des3 1024 > $USERDIR/private/cakey.pem" );
system("ssleay req -new -x509 -key $USERDIR/private/cakey.pem -out $USERDIR/cacert.pem $DAYS");
	chmod 0600, "$USERDIR/private/cakey.pem";
	print "certificate authority (CA) directory is in \"$USERDIR\"\n";

	if ( ! -w $SSLEAYLIB ) {
		print "Error: $!., script needs write permission to\n";
		print "SSLEAYLIB/\n";
		print "You will need to fix this and place a world-readable copy\n";
		print "of \"$USERDIR/cacert.pem\" in \"$SSLEAYLIB\"\n";
		print "before certificate requests can be created\n";
		exit $!;
	}
	print "copying new CA certficate to $SSLEAYLIB\n";
	if ( system( "cp -i $USERDIR/cacert.pem $SSLEAYLIB/" ) ) {
		print "Error: copying $USERDIR/cacert.pem to $SSLEAYLIB/, $!\n";
		print "You will need to place a world-readable copy\n";
		print "of \"$USERDIR/cacert.pem\" in \"$SSLEAYLIB\"\n";
		print "before certificate requests can be created or signed by this CA\n";
		exit $!;
	}
	chmod 0644, "$SSLEAYLIB/cacert.pem";
}
else { #-help
	print "usage: condor_cert.pl {-user|-daemon|-createca} <directory path>\n";
	print "   or: condor_cert.pl -signcert -infile <infile> <output file>\n";
	exit 0;
}

#eval $CLEANUP;   # do any option specific cleanup
