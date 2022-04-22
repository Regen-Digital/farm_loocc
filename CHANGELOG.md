# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## 1.0.1 2022-04-22

### Added

- Add warning indicator to display warning messages. [#3](https://github.com/paul121/farm_loocc/issues/3)
- Collapse views filters in loocc estimate view. [#13](https://github.com/paul121/farm_loocc/issues/13)
- Page with tables of ERF method co-benefits. [#7](https://github.com/paul121/farm_loocc/issues/7)
- Dynamically compute SOC values. [#11](https://github.com/paul121/farm_loocc/issues/11)
- Delete estimates when assets are archived. [#10](https://github.com/paul121/farm_loocc/issues/10)
- Add `deleteEstimate` function to `farm_loocc.estimate` service.

## 1.0.0 2022-04-02

The initial release of this module.

### Added

- A table view of LOOC-C estimates and methods with interactive AJAX features.
- A form for creating LOOC-C estimates using batch operations.
- The `farm_loocc.estimate` service with helper methods for creating estimates.
- The `farm_loocc.loocc_client` service for interacting with LOOC-C API.
