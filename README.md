
# Swift Auth

Swift Auth is a robust and efficient system designed for user management, providing an intuitive and scalable solution to manage access and permissions.


## Environment Variables

To run this project, you will need to add the following environment variables to your .env file

`SWIFT_AUTH_FRONTEND`

`SWIFT_AUTH_SUCCESS_URL`


the environment variable can only have one of the following values: **typescript | blade** | **javascript**.

**example:**

`SWIFT_AUTH_FRONTEND` = typescript


with `SWIFT_AUTH_SUCCESS_URL` indicates which path to go to once logged in.

**example:**

`SWIFT_AUTH_SUCCESS_URL`= '/dashboard'

## Installation

- Create project
- cd my-project



**Swift auth Installation**
```bash
  php artisan swift-auth:install
``` 
**Once the command is executed, it asks if you want to publish different files**

# Note:  
### if you want to work locally with the swift auth repository

- clone the swift auth repository to the same height as your main project 

- add the following to´your `composer.json` file:

```bash
  "repositories": [
        {
            "type": "path",
            "url": "route-to-folder/com.teleurban.swift_auth",
            "options": {
                "symlink": true
            }
        }
    ],
``` 
