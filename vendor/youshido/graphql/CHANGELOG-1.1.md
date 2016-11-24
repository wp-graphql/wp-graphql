# Changelog

### v1.1 – Relay support and Schema structure

#### General changes
- Relay support added
- Test coverage 100% added
- `AbstractField` was introduced and `AbstractType` was changed (see [upgrade-1.2](UPGRADE-1.2.md))

#### Processor
- Processor now requires an `AbstractSchema` in `__construct`
- `->processRequest($payload, $variables = [])` changed to `->processPayload(string $payload, $variables = [])`

#### Type
- parameter `resolve` was removed from config
- parameter `args` was removed from config
- parameter `fields` is now required when you create an instance of `ObjectType`

#### Field
- `AbstractField` was introduced
- `Field` class now has to be used to define a field inside `fields` config
- abstract `resolve` methods added
