<?php namespace Models\Account\Services;

use kornrunner\Keccak;
use Web3\Web3;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;

class ENSResolver
{
    private Web3 $web3;
    private string $ensRegistryAddress = '0x00000000000C2E074eC69A0dFb2997BA6C7d2e1e';

    public function __construct(string $providerUrl)
    {
        $this->web3 = new Web3(new HttpProvider(new HttpRequestManager($providerUrl)));
    }

    public function getENSData(string $addressOrName): array
    {
        $result = [
            'name' => null,
            'avatar' => null,
            'twitter' => null,
            'github' => null,
            'url' => null
        ];

        try {
            if (str_starts_with(strtolower($addressOrName), '0x')) {
                // It's an address, do reverse lookup
                $name = $this->getNameFromAddress($addressOrName);
                if ($name) {
                    $result['name'] = $name;
                    $this->populateRecords($name, $result);
                }
            } else {
                // It's a name, resolve it directly
                $result['name'] = $addressOrName;
                $this->populateRecords($addressOrName, $result);
            }
        } catch (\Exception $e) {
            // Log error but don't throw
            error_log("ENS Resolution error: " . $e->getMessage());
        }

        return $result;
    }

    private function getNameFromAddress(string $address): ?string
    {
        // Normalize the address to lowercase
        $address = strtolower($address);

        // Remove '0x' if present and ensure proper formatting
        $cleanAddress = str_starts_with($address, '0x') ? substr($address, 2) : $address;

        // Construct the reverse lookup name
        $reverseName = $cleanAddress . '.addr.reverse';

        // Generate namehash for the reverse record
        $nameHash = $this->namehash($reverseName);

//        Debugger::barDump([
//            'original' => $address,
//            'clean' => $cleanAddress,
//            'reverseName' => $reverseName,
//            'nameHash' => $nameHash
//        ], 'Reverse Resolution');

        // Get the resolver for this reverse record
        $resolverAddress = $this->getResolver($nameHash);

        if (!$resolverAddress) {
//            Debugger::barDump('No resolver found for reverse record');
            return null;
        }

        // Call the name() function on the resolver
        $data = '0x691f3431' . substr($nameHash, 2); // name(bytes32)

        $result = null;
        $this->web3->eth->call(
            [
                'to' => $resolverAddress,
                'data' => $data,
                'from' => '0x0000000000000000000000000000000000000000'
            ],
            'latest',
            function ($err, $response) use (&$result) {
//                Debugger::barDump([
//                    'error' => $err,
//                    'response' => $response
//                ], 'Name lookup response');

                if ($err === null && !empty($response) && $response !== '0x') {
                    $result = $response;
                }
            }
        );

        if (!$result) {
            return null;
        }

        // Decode the name from the response
        $name = $this->decodeString($result);

//        Debugger::barDump([
//            'address' => $address,
//            'resolved_name' => $name
//        ], 'Resolved Name');

        return $name;
    }

    private function populateRecords(string $name, array &$result): void
    {
        $nameHash = $this->namehash($name);
        $resolverAddress = $this->getResolver($nameHash);

        if (!$resolverAddress) {
            return;
        }

        // Map ENS keys to result keys
        $records = [
            'avatar' => 'avatar',
            'com.twitter' => 'twitter',
            'com.github' => 'github',
            'url' => 'url'
        ];

        foreach ($records as $ensKey => $resultKey) {
            $value = $this->getText($resolverAddress, $nameHash, $ensKey);
            if ($value) {
                $result[$resultKey] = $value;
            }
        }
    }

    private function getResolver(string $node): ?string
    {
        $result = null;
        $this->web3->eth->call(
            [
                'to' => $this->ensRegistryAddress,
                'data' => '0x0178b8bf' . substr($node, 2)
            ],
            'latest',
            function ($err, $response) use (&$result) {
                if ($err === null && !empty($response) &&
                    $response !== '0x' &&
                    $response !== '0x0000000000000000000000000000000000000000000000000000000000000000') {
                    $result = '0x' . substr($response, 26);
                }
            }
        );

        return $result;
    }

    private function getText(string $resolverAddress, string $node, string $key): ?string
    {
        // Encode function call
        $selector = '59d1d43c';
        $encodedNode = substr($node, 2);
        $stringOffset = str_pad('40', 64, '0', STR_PAD_LEFT);
        $stringLength = str_pad(dechex(strlen($key)), 64, '0', STR_PAD_LEFT);
        $stringData = str_pad(bin2hex($key), ceil(strlen(bin2hex($key)) / 64) * 64, '0', STR_PAD_RIGHT);

        $data = '0x' . $selector . $encodedNode . $stringOffset . $stringLength . $stringData;

        $result = null;
        $this->web3->eth->call(
            [
                'to' => $resolverAddress,
                'data' => $data
            ],
            'latest',
            function ($err, $response) use (&$result) {
                if ($err === null && !empty($response) &&
                    $response !== '0x' &&
                    $response !== '0x0000000000000000000000000000000000000000000000000000000000000000') {
                    $result = $response;
                }
            }
        );

        if (!$result) {
            return null;
        }

        return $this->decodeString($result);
    }

    private function decodeString(string $hexString): ?string
    {
        try {
            $hex = substr($hexString, 2);
            $offset = hexdec(substr($hex, 0, 64));
            $length = hexdec(substr($hex, $offset * 2, 64));

            if ($length === 0) {
                return null;
            }

            $stringHex = substr($hex, ($offset * 2) + 64, $length * 2);
            return hex2bin($stringHex);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function namehash(string $name): string
    {
        $node = str_repeat('0', 64);

        if ($name) {
            $labels = array_reverse(explode('.', $name));
            foreach ($labels as $label) {
                $node = Keccak::hash(hex2bin($node) . hex2bin(Keccak::hash($label, 256)), 256);
            }
        }

        return '0x' . $node;
    }
}