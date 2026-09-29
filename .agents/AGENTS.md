# Project Architecture & Global Constraints

The following rules dictate the fundamental architecture and development boundaries for the Pharma-CRM project. They must be adhered to at all times.

## 1. Cloud Super-Admin & Common Backend
`E:\Projects\PHP\Pharma-CRM` is the full cloud-super-admin-cum-common-back-end for the entire project.
- It operates under a sellable public subscription/franchise-mode.
- Admins logging into this system are the "owners" of a subset, with visibility strictly limited to their franchise, teams, and roles.

## 2. Front-End Separation & Sync
`E:\Projects\PHP\Pharma-CRM\crmpharma` is a custom front-end that operates as an independent server/individual admin-franchise-host.
- It mandatory connects, syncs, and maintains a connection to the primary cloud (`E:\Projects\PHP\Pharma-CRM`) under the limited admin and subset constraints.

## 3. Strict Back-End Only Modification Policy
- **DO NOT** make modifications to the front-end code.
- Always follow the front-end FRS (Functional Requirement Specification), docs, screens, and inputs.
- Write back-end code strictly according to front-end requirements.
- The front-end will mandatory pull APIs and data (Zero-Local-Front-End-Caching).
- Develop all maximum permutation/combination endpoints inside the common super admin back-end to allow sellable admins/front-ends to connect easily and flawlessly.

## 4. Architecture & Scalability
- The back-end must be highly scalable, reliable, and micro-service layered.
- It is designed to "live forever" for all open public domains and ports.
- It operates via the admin-franchise-mode subset visibility only.

## 5. Professional Standard
- Maintain production-grade, professional-level code quality.
- All code updates and logic improvements must be strictly confined to the back-end infrastructure.
