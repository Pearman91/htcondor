/***************************************************************
 *
 * Copyright (C) 1990-2015, Condor Team, Computer Sciences Department,
 * University of Wisconsin-Madison, WI.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License"); you
 * may not use this file except in compliance with the License.  You may
 * obtain a copy of the License at
 * 
 *    http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 ***************************************************************/

 
#include "condor_common.h"
#include "condor_attributes.h"
#include "condor_classad.h"
#include "condor_config.h"
#include "condor_constants.h"
#include "condor_md.h"
#include "directory_util.h"
#include "filename_tools.h"
#include "stat_wrapper.h"


// Filenames are case insensitive on Win32, but case sensitive on Unix
#ifdef WIN32
#	define file_contains contains_anycase
#else
#	define file_contains contains
#endif


using namespace std;

namespace {	// Anonymous namespace to limit scope of names to this file
  
const int HASHNAMELEN = 17;


static string MakeHashName(const char *fileName) {
  unsigned char result[HASHNAMELEN * 3]; // Allocate extra space for safety.
  memcpy(result, Condor_MD_MAC::computeOnce((unsigned char *) fileName,
	  strlen(fileName)), HASHNAMELEN);
  char entryhashname[HASHNAMELEN * 2]; // 2 chars per hex byte
  entryhashname[0] = '\0';
  char letter[3];
  for (int ind = 0; ind < HASHNAMELEN - 1; ++ind) {
	  sprintf(letter, "%x", result[ind]);
	  strcat(entryhashname, letter);
  }
  return (entryhashname);
}


static bool MakeLink(const char *const srcFile, const string &newLink) {
  const char *const webRootDir = param("WEB_ROOT_DIR");
  if (webRootDir == NULL) {
    dprintf(D_ALWAYS, "WEB_ROOT_DIR not set\n");
    return (false);
  }
  char goodPath[PATH_MAX];
  if (realpath(webRootDir, goodPath) == NULL) {
    dprintf(D_ALWAYS, "WEB_ROOT_DIR not a valid path: %s\n", webRootDir);
    return (false);
 }
  StatWrapper fileMode;
  bool fileOK = false;
  if (fileMode.Stat(srcFile) == 0) {
    const StatStructType *statrec = fileMode.GetBuf();
    if (statrec != NULL)
      fileOK = (statrec->st_mode & S_IROTH); // Verify readable by all
  }
  if (fileOK == false) {
    dprintf(D_ALWAYS,
      "Cannot transfer -- public input file not world readable: %s\n", srcFile);
    return (false);
  }
  const char *const targetLink = dircat(goodPath, newLink.c_str()); // needs to be freed
  bool retVal = false;
  if (targetLink != NULL) {
    if (fileMode.Stat(targetLink, StatWrapper::STATOP_LSTAT) == 0) { // Check if target already exists
      retVal = true; // Good enough if link exists, ok if update fails
      // It is assumed that existing link points to srcFile
      const StatStructType *statrec = fileMode.GetBuf();
      if (statrec != NULL) {
      	time_t filemtime = statrec->st_mtime;
      	// Must be careful to operate on link, not target file.
      	if ((time(NULL) - filemtime) > 3600 && lutimes(targetLink, NULL) != 0) {
      	  // Update mod time, but only once an hour to avoid excess file access
	  dprintf(D_ALWAYS, "Could not update modification date on %s, error = %s\n",
	    targetLink, strerror(errno));
     	}
      } else dprintf(D_ALWAYS, "Could not stat file %s\n", targetLink);
    } else if (symlink(srcFile, targetLink) == 0) {
      retVal = true;
    } else dprintf(D_ALWAYS, "Could not link %s to %s, error = %s\n", srcFile,
       targetLink, strerror(errno));
    delete [] targetLink;
  }
  return (retVal);
}

} // end namespace


static string MakeAbsolutePath(const char *path, const char *const iwd) {
  if (is_relative_to_cwd(path)) {
    string fullpath = iwd;
    fullpath += DIR_DELIM_CHAR;
    fullpath += path;
    return (fullpath);
  }
  return (path);
}


void ProcessCachedInpFiles(ClassAd *const Ad, StringList *const InputFiles,
  StringList &PubInpFiles) {
  if (PubInpFiles.isEmpty() == false) {
    const char *webSrvrPort = param("WEB_SERVER_PORT");
    string url = "http://";
    url += webSrvrPort;
    url += "/";
    PubInpFiles.rewind();
    const char *path;
    MyString remap;
    char *iwd = NULL;
    if (Ad->LookupString(ATTR_JOB_IWD, &iwd) != 1) {
      dprintf(D_FULLDEBUG, "mk_cache_links.cpp: Job ad did not have an iwd!\n");
      return;
    }
    while ((path = PubInpFiles.next()) != NULL) {
      string fullpath = MakeAbsolutePath(path, iwd);
      string hashName = MakeHashName(fullpath.c_str());
      if (MakeLink(fullpath.c_str(), hashName)) {
	InputFiles->remove(path); // Remove plain file name from InputFiles
	remap +=hashName;
	remap += "=";
	remap += basename(path);
	remap += ";";
	hashName = url + hashName;
	const char *const namePtr = hashName.c_str();
	if ( !InputFiles->file_contains(namePtr) ) {
	  InputFiles->append(namePtr);
	  dprintf(D_FULLDEBUG, "Adding url to InputFiles: %s\n", namePtr);
	} else dprintf(D_FULLDEBUG, "url already in InputFiles: %s\n", namePtr);
      }
    }
    free(iwd);
    if (remap.Length() > 0) {
      MyString remapnew;
      char *buf = NULL;
      if (Ad->LookupString(ATTR_TRANSFER_INPUT_REMAPS, &buf) == 1) {
	remapnew = buf;
	free(buf);
	buf = NULL;
	remapnew += ";";
      } 
      remapnew += remap;
      if (Ad->Assign(ATTR_TRANSFER_INPUT_REMAPS, remap.Value()) == false) {
	dprintf(D_ALWAYS, "Could not add to jobAd: %s\n", remap.c_str());
      }
    }
  } else  dprintf(D_FULLDEBUG, "No public input files.\n");
}
