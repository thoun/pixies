# What is this project ? 
This project is an adaptation for BoardGameArena of game Sea Salt & Paper edited by Bombyx.
You can play here : https://boardgamearena.com

# How to install the auto-build stack

## Install builders
Intall node/npm then `npm i` on the root folder to get builders.

## Auto build JS and CSS files
In VS Code, add extension https://marketplace.visualstudio.com/items?itemName=emeraldwalk.RunOnSave and then add to config.json extension part :
```json
        "commands": [
            {
                "match": ".*\\.ts$",
                "isAsync": true,
                "cmd": "npm run build:ts"
            },
            {
                "match": ".*\\.scss$",
                "isAsync": true,
                "cmd": "npm run build:scss"
            }
        ]
```
If you use it for another game, replace `pixies` mentions on package.json `build:scss` script and on tsconfig.json `files` property.

## Auto-upload builded files
Also add one auto-FTP upload extension (for example https://marketplace.visualstudio.com/items?itemName=lukasz-wronski.ftp-sync) and configure it. The extension will detected modified files in the workspace, including builded ones, and upload them to remote server.

## Hint
Make sure ftp-sync.json and node_modules are in .gitignore

# Notifs
To get all notifs of a game replay, set in replay console :
```js
const studioTable = 483521;
const studioFirstPlayer = 2343492;
JSON.stringify(g_gamelogs).replace(/(\d{6,})/g, n => {
    const index = Object.values(gameui.gamedatas.playerorder).map(val => ''+val).indexOf(n);
    if (index !== -1) {
        return `${studioFirstPlayer + index}`;
    } else if (n == gameui.table_id) {
        return `${studioTable}`;
    } else {
        return n;
    }
});

```
And copy the result

Then in studio
```js
json = <paste copied result from previous command>;

let gls = JSON.parse(json);
g_archive_mode = true;
gameui.updateReflexionTime = () => {};
gameui.initCommentsForMove = () => {};
gls.forEach(gl => {
    try {
        gameui.notifqueue.onNotification(gl)
    } catch (e) {
        console.warn(e);
    }
});
```