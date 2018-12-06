/***************************************************************
 *
 * Copyright (C) 2018, Condor Team, Computer Sciences Department,
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

#ifndef _CONDOR_EC2_PROC_H
#define _CONDOR_EC2_PROC_H

//
// #include "os_proc.h"
//

class EC2Proc : public OsProc {
	public:
		EC2Proc( ClassAd * jobAd );
		virtual ~EC2Proc();

		virtual int StartJob();
		virtual bool JobReaper( int pid, int status );
		virtual bool JobExit();
		virtual bool PublishUpdateAd( ClassAd * ad );
		virtual void PublishToEnv( Env * e );
		virtual void Suspend();
		virtual void Continue();
		virtual bool Remove();
		virtual bool Hold();
		virtual bool ShutdownGraceful();
		virtual bool ShutdownFast();

	protected:
		int jobAdPipeID;
		int updateAdPipeID;
		int stderrPipeID;
};

#endif /* _CONDOR_EC2_PROC_H */
