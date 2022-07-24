# WebConsole

> API server and modernized control panel for PocketMine servers.

The WebConsole plugin provides an HTTP API server
that can be extended with other plugins through intuitive API
involving minimal logic related to web development
so that developers can focus on the actual logic and let WebConsole handle the GUI.

The project comes with a static web app that connects to this API server,
providing visualization of server data and a user-friendly GUI to control the server.

## Objectives

### Goals

- Provide a basic medium of inter-process communication to control a PocketMine server.
- Provide an extensible API that allows plugins to
  expose their functionality to other processes.
- Provide a user-friendly GUI to view server data and trigfer actions on the server,
  eventually phasing out stdin commands.

### Non-goals

- WebConsole does not implement any permission management or authentication.
  Hence, it should not be a publicly accessible API.
  It only serves as an RPC server, but does not come with any permission management.
  Any access to the WebConsole HTTP server, other than access by the sole super user,
  must be relayed through a more secure layer that implements authentication.
- WebConsole is not a server deployment manager.
  It does not control the startup/shutdown lifecycle of a server.
  The WebConsole HTTP server runs inside a PocketMine server, not the other way round.
- WebConsole is not scalable for network management.
  While it is designed to serve as a building block for centralized network management,
  it is not designed to be connected from every other node in a server network.
  A central network manager can call WebConsole APIs to control an individual server,
  but WebConsole itself should not be used as the network manager itself.

## Concepts

### Objects

Objects are the primary interface for WebConsole.
Objects are grouped into different *kinds*,
where the web app would display a list of each object kind.

Builtin object kinds include:

- Online Player
- Account (including online and offline players)
- World
- Server (only has one object)
- WebConsole (only has one object)

Other plugins can register new object kinds.
The plugin that registers the object kind
is responsible for notifying the creation and deletion of its objects.

### Details

Details are data associated to an object.
Details can be of any scalar or complex type.
The following types have builtin support for WebConsole web app:

- String (including specialized types like datetime and enums)
- Number
- Boolean
- Time series
- References to other objects
- Lists/Structs of the types above

Other plugins can register new object details for existing object kinds.
Detail provision is async, continuous and active,
which means other plugins need to notify WebConsole API
when an object detail is updated.

### Mutations

While objects and details provide read access to the server,
mutations allow clients to actively modify the server.

Plugins can register new mutations with explicitly declared parameter types.
If a parameter type is an object, it gets linked in the object display page.
For example, a mutation called "ban player" accepts an Account object parameter,
so a "ban player" button is available from each account display page.

Mutation responses can be progressive.
Plugins can return an asynchronous stream of "progress snapshots"
that are rendered in the client similar to detail views.
A mutation completes when server returns with a snapshot that flags an end-of-stream.
