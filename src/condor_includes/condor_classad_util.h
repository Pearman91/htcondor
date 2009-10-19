/***************************************************************
 *
 * Copyright (C) 1990-2007, Condor Team, Computer Sciences Department,
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


#ifndef CONDOR_CLASSAD_UTIL_H
#define CONDOR_CLASSAD_UTIL_H

#include "condor_classad.h"
#include "string_list.h"

bool EvalBool(ClassAd *ad, const char *constraint);
bool EvalBool(AttrList* ad, ExprTree *tree);

bool IsAMatch( const ClassAd *ad1, const ClassAd *ad2 );
bool IsAHalfMatch( const ClassAd *ad1, const ClassAd *ad2 );

bool ClassAdsAreSame( ClassAd* ad1, ClassAd* ad2, 
					  StringList* ignored_attrs=NULL, bool verbose=false );

#endif
