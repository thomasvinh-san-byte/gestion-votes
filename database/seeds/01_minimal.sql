-- database/seeds/01_minimal.sql
-- Seed minimal compatible avec database/schema.sql (idempotent).
-- Mot de passe par defaut pour tous les utilisateurs : Changez-moi1
-- Changez les mots de passe via l'admin (/api/v1/admin_users.php action=set_password).

BEGIN;

-- Tenant par défaut (doit matcher DEFAULT_TENANT_ID dans app/bootstrap.php)
INSERT INTO tenants (id, name, slug, timezone)
VALUES ('aaaaaaaa-1111-2222-3333-444444444444', 'Collectivité démo', 'demo', 'Europe/Paris')
ON CONFLICT (id) DO UPDATE
SET name = EXCLUDED.name,
    slug = EXCLUDED.slug,
    timezone = EXCLUDED.timezone,
    updated_at = now();

-- Politiques quorum (quelques presets)
INSERT INTO quorum_policies (
  id, tenant_id, name, description, mode, denominator, threshold, threshold_call2, denominator2, threshold2,
  include_proxies, count_remote, created_at, updated_at
) VALUES
  ('11111111-1111-1111-1111-111111111001','aaaaaaaa-1111-2222-3333-444444444444','Aucun quorum','Consultatif', 'single','eligible_members',0.0,NULL,NULL,NULL,true,true,now(),now()),
  ('11111111-1111-1111-1111-111111111002','aaaaaaaa-1111-2222-3333-444444444444','Quorum 33% (personnes)','', 'single','eligible_members',0.33333,NULL,NULL,NULL,true,true,now(),now()),
  ('11111111-1111-1111-1111-111111111003','aaaaaaaa-1111-2222-3333-444444444444','Quorum 50% (personnes)','', 'single','eligible_members',0.5,NULL,NULL,NULL,true,true,now(),now()),
  ('11111111-1111-1111-1111-111111111004','aaaaaaaa-1111-2222-3333-444444444444','Quorum 50% (pondéré)','', 'single','eligible_weight',0.5,NULL,NULL,NULL,true,true,now(),now()),
  ('11111111-1111-1111-1111-111111111005','aaaaaaaa-1111-2222-3333-444444444444','Quorum évolutif 50%→0%','2e convocation sans quorum', 'evolving','eligible_members',0.5,0.0,NULL,NULL,true,true,now(),now())
ON CONFLICT (tenant_id, name) DO UPDATE
SET description = EXCLUDED.description,
    mode = EXCLUDED.mode,
    denominator = EXCLUDED.denominator,
    threshold = EXCLUDED.threshold,
    threshold_call2 = EXCLUDED.threshold_call2,
    denominator2 = EXCLUDED.denominator2,
    threshold2 = EXCLUDED.threshold2,
    include_proxies = EXCLUDED.include_proxies,
    count_remote = EXCLUDED.count_remote,
    updated_at = now();

-- Politiques vote (quelques presets)
INSERT INTO vote_policies (
  id, tenant_id, name, description, base, threshold, abstention_as_against, created_at, updated_at
) VALUES
  ('22222222-2222-2222-2222-222222222001','aaaaaaaa-1111-2222-3333-444444444444','Majorité simple','> 50% des exprimés','expressed',0.5,false,now(),now()),
  ('22222222-2222-2222-2222-222222222002','aaaaaaaa-1111-2222-3333-444444444444','Majorité absolue','> 50% des éligibles','total_eligible',0.5,false,now(),now()),
  ('22222222-2222-2222-2222-222222222003','aaaaaaaa-1111-2222-3333-444444444444','Majorité 2/3','>= 66,66% des exprimés','expressed',0.66667,false,now(),now()),
  ('22222222-2222-2222-2222-222222222004','aaaaaaaa-1111-2222-3333-444444444444','Majorité 3/5','>= 60% des exprimés','expressed',0.6,false,now(),now())
ON CONFLICT (tenant_id, name) DO UPDATE
SET description = EXCLUDED.description,
    base = EXCLUDED.base,
    threshold = EXCLUDED.threshold,
    abstention_as_against = EXCLUDED.abstention_as_against,
    updated_at = now();

-- Users RBAC avec mot de passe par défaut : Changez-moi1
-- Rôles SYSTÈME : admin, operator, auditor, viewer
-- Rôles SÉANCE (meeting_roles) : president, assessor, voter
INSERT INTO users (id, tenant_id, email, name, role, password_hash, is_active, created_at, updated_at)
VALUES
  ('33333333-3333-3333-3333-333333333001','aaaaaaaa-1111-2222-3333-444444444444','admin@example.test','Administrateur','admin','$2y$12$BcE0KAUTeRz0HCyGyO9E6uedpJD/m/HZPI3JBbv5gIylGTV0Qd2Tq',true,now(),now()),
  ('33333333-3333-3333-3333-333333333002','aaaaaaaa-1111-2222-3333-444444444444','operator@example.test','Opérateur','operator','$2y$12$BcE0KAUTeRz0HCyGyO9E6uedpJD/m/HZPI3JBbv5gIylGTV0Qd2Tq',true,now(),now()),
  ('33333333-3333-3333-3333-333333333003','aaaaaaaa-1111-2222-3333-444444444444','president@example.test','Mme Dupont','operator','$2y$12$BcE0KAUTeRz0HCyGyO9E6uedpJD/m/HZPI3JBbv5gIylGTV0Qd2Tq',true,now(),now()),
  ('33333333-3333-3333-3333-333333333004','aaaaaaaa-1111-2222-3333-444444444444','assessor@example.test','M. Martin','viewer','$2y$12$BcE0KAUTeRz0HCyGyO9E6uedpJD/m/HZPI3JBbv5gIylGTV0Qd2Tq',true,now(),now()),
  ('33333333-3333-3333-3333-333333333005','aaaaaaaa-1111-2222-3333-444444444444','auditor@example.test','Auditeur','auditor','$2y$12$BcE0KAUTeRz0HCyGyO9E6uedpJD/m/HZPI3JBbv5gIylGTV0Qd2Tq',true,now(),now())
ON CONFLICT (id) DO UPDATE
SET email = EXCLUDED.email,
    name = EXCLUDED.name,
    role = EXCLUDED.role,
    password_hash = EXCLUDED.password_hash,
    is_active = EXCLUDED.is_active,
    updated_at = now();

COMMIT;