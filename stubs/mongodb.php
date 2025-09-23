<?php
// Stubs IDE uniquement (pour Intelephense)
namespace MongoDB\BSON {
    class ObjectId { public function __construct(?string $id = null) {} public function __toString(){} }
    class UTCDateTime { public function __construct(int|string|null $milliseconds = null) {} }
}
namespace MongoDB\Operation {
    class FindOneAndUpdate {
        public const RETURN_DOCUMENT_BEFORE = 0;
        public const RETURN_DOCUMENT_AFTER  = 1;
    }
}
namespace MongoDB\BSON {
    class UTCDateTime {
        public function __construct(int|string|null $ms = null) {}
        public function toDateTime(): \DateTime { return new \DateTime(); }
    }
}
