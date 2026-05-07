function bufferToHex(bytes) {
  const out = new Array(bytes.length)
  for (let i = 0; i < bytes.length; i++) {
    out[i] = bytes[i].toString(16).padStart(2, "0")
  }
  return out.join("")
}

export async function sha256(input) {
  if (typeof input !== "string") {
    throw new TypeError("sha256: input must be a string")
  }
  const subtle = globalThis.crypto?.subtle
  if (!subtle) {
    throw new Error(
      "next-wpgraphql/hash: Web Crypto (globalThis.crypto.subtle) is not available in this runtime"
    )
  }
  const bytes = new TextEncoder().encode(input)
  const buf = await subtle.digest("SHA-256", bytes)
  return bufferToHex(new Uint8Array(buf))
}
