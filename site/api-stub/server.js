const fs = require("fs/promises")
const path = require("path")
const yaml = require("js-yaml")
require("http").createServer(async (req, res) => {
	let url = req.url
	if(url.startsWith("/")) {
		url = url.substr(1)
	}

	async function checkPath(p) {
		try {
			const path = await fs.realpath(p)
			if(!path.startsWith(__dirname)) {
				return [403, "forbidden"]
			}
		} catch(err) {
			return [404, "not found"]
		}
	}

	let p = path.join(__dirname, url)

	let ret = await checkPath(p)
	if(ret) {
		p += ".yml"
		ret = await checkPath(p)
		if(ret) {
			res.statusCode = ret[0]
			res.write(ret[1])
			res.end()
			return
		}
	}

	let read
	try {
		read = await fs.readFile(p)

		if(p.endsWith(".yml")) {
			read = JSON.stringify(yaml.load(read))
		}
	} catch(err) {
		res.statusCode = 500
		res.write(err.toString())
		res.end()
		return
	}

	res.setHeader("Access-Control-Allow-Origin", "*")
	res.write(read)
	res.end()
}).listen(5050)
