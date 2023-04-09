# WebConsole

> API server and modernized control panel for PocketMine servers.

The WebConsole plugin runs an HTTP server
that provides an object-oriented declarative REST API
to interact with server logic.
It exposes a plugin API focused on business logic
so that plugins can expose their features through WebConsole
without involving anh web-specific logic.

The project also offers a webapp that connects to this API server
to visualize server data and control the server without using commands.

## How to use

1. Install the [WebConsole plugin](https://sof3.github.io/WebConsole/WebConsole.phar) on your server.
2. 

## Objectives

### Goals

- Provide a mchine-friendly form of inter-process communication to control a PocketMine server,
  in contrast to unreliable, fuzzy interfaces like RCON.
- Provide an extensible API that allows plugins to
  expose their functionality to other processes.
- Provide a user-friendly GUI to view server data and trigger actions on the server,
  eventually phasing out stdin commands.

### Non-goals

- WebConsole does not implement any permission management or authentication.
  Hence, it should not be a publicly accessible API.
  Production deployment should hide the API server behind a secure proxy sidecar.
- WebConsole is not a server deployment manager.
  It does not control the startup/shutdown lifecycle of a server.
  The WebConsole HTTP server runs inside a PocketMine server, not the other way round.
  However, hosts that implement a server manager could frame the WebConsole GUI inside theirs.
- WebConsole is not scalable for network management.
  While it is designed to serve as a building block for centralized network management,
  it is not designed to be connected from every other node in a server network.
  A central network manager can call WebConsole APIs to control an individual server,
  but WebConsole itself should not be used as the network manager.

## Concepts

### Objects

An object is something with a defined type and a unique name.
An object could be live (e.g. worlds, players), temporary (e.g. chat messages),
lazy (e.g. offline player data) and singleton (e.g. plugin configuration).

API clients can list all objects of a kind,
or monitor the creation and deletion of such objects.

### Fields

A field contains some data associated with an object.
Fields are provided separately from objects.
For example, an economy plugin can provide a field to player objects
to indicate the amount of money the player owns.
The field data are included under the object root in list/watch/get responses.

The type of data returned by field providers is arbitrary,
but they must be JSON-serializable that can be described using WebConsole type schema.
The following types are supported:

- String
- Enum
- Timestamp
- Integer, Float, Boolean
- References to other objects
- Optional/List/Compound/Union of the types above

Field providers are responsible for pushing updates of fields in listed objects.

### Object creation

Object providers may provide the ability to create objects.
Fields supplied by the API client during object creation
are passed to the corresponding field providers to
prepare/postprocess the created object.

The semantics of object creation is subject to object provider interpretation.

### Object deletion

Object providers may provide the ability to delete objects.
The semantics of object deletion is subject to object provider interpretation.

### Object updates

API clients may update an object by sending the modified fields of an object.
Fields in update requests are directly processed by the corresponding field providers.

Note that object updates may not necessarily be reflected in watch/get requests.
