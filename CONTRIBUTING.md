# Contributing to ElkArte

* ElkArte is licensed under [BSD 3-clause license](http://www.opensource.org/licenses/BSD-3-Clause).
* Documentation is licensed under [CC-by-SA 3](http://creativecommons.org/licenses/by-sa/3.0).
* Third party libraries or sets of images, are under their own licenses.

## Before You Start

Anyone wishing to contribute to the **[ElkArte](https://github.com/elkarte/Elkarte)** project **MUST** agree to distribute their contributions under the [BSD 3 Clause license](https://github.com/elkarte/Elkarte/blob/master/LICENSE.txt) applied to the project.

## How to Contribute:

* Fork / Clone the repository. ```git clone git://github.com/elkarte/ElkArte.git ``` If you are not used to using Github, please read the [fork a repository](http://help.github.com/fork-a-repo).
* Create a new Branch your own repository. 
  * ```cd elkarte```
  * ```git checkout -b new_branch_enhancement_name```
  * Please do not commit to your 'master' branch, or branch off and cherry-pick commits before sending in a PR.
* Code
  * Adhere to conventions you see in the existing code
  * Adhere to the [Coding standards](https://github.com/elkarte/Elkarte/wiki/Coding-Standards) for the project.
* Commit your changes to that branch. As many commits on 'new_branch_enhancement_name' as you want.
  * ```git commit -a```
  * Granular commits are not a problem.
  * Please try to add only commits related to the respective feature/fix to a branch.
  * If you need to commit something unrelated, create another branch for that topic.
* Push your changes to your remote
  * ```git push mine new_branch_enhancement_name```
  * **NEVER leave the commit message empty** Please provide a clear, and complete description of your commit!
* Send in a Pull Request (PR) from your new_branch_enhancement_name branch.
  * Navigate to the ElkArte repository you just pushed to (e.g. https://github.com/your-user-name/elkarte)
  * Click "Pull Request".
  * Ensure the changes you introduced are included in the "Commits" tab.
  * Ensure that the "Files Changed" incorporate all of your changes and not any extra files you may have touched.
  * Fill in some details about your potential patch including a meaningful title and description.
  * Click "Send pull request".
* **NOTE** If the main repository has been updated in the meantime, you can pull the master changes in your repository:
  * To pull in from master to your master branch: ``` git pull --rebase upstream master ``` (please replace 'upstream' to the name of your remote for the main repository)
  * To merge after you have updated your master, to your _new_enhancement_ branch:
     * preferred: ``` git rebase master ```
     * alternatively: ``` git merge master ``` (this may create a merge commit by default)

Details about the rebase operation:
[git rebase] (http://www.kernel.org/pub/software/scm/git/docs/git-rebase.html)

Recommended reading for github pull requests workflow:
[About Github workflow](http://qsapp.com/wiki/Github#Github_Contributor_Workflow)

## Code Reviews

An important and very helpful part of the workflow is code reviews.

The more eyes are on the code, on the PRs, the better: one can always spot something missed, a small detail, a serious bug, or start a conversation on the code that will help in the end reach a better solution for the software.  Part of interacting with a healthy open-source community requires you to be open as well, so *don't get discouraged!* Remember: if changes are suggested to your code, **they care enough about your work that they want to include it!**

Code reviews can be made from the github web interface, or by checking out of the code pulled in locally. You can also test the PRs. Please do, express your objections or approval, fix things or report them.

## Quick commands on how to pull in PRs:
https://gist.github.com/3342247

## Developer Documentation
* Coding standards documentation: [Coding standards](https://github.com/elkarte/Elkarte/wiki/Coding-Standards)
* Architecture documentation: [Architecture](https://github.com/elkarte/Elkarte/wiki/Architecture)

Each of the above are works in progress, and welcomes your comments and corrections.
