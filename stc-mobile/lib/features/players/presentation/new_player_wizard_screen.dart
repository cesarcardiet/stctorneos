import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/errors/app_exception.dart';
import '../../../core/network/dio_client.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_field.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_form_field.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../data/player_roster_repository.dart';

final playerRosterRepositoryProvider = Provider<PlayerRosterRepository>((ref) {
  return PlayerRosterRepository(ref.watch(dioClientProvider));
});

class NewPlayerWizardScreen extends ConsumerStatefulWidget {
  const NewPlayerWizardScreen({
    super.key,
    this.categoryId,
    this.teamId,
    this.playerId,
  });

  final int? categoryId;
  final int? teamId;
  final int? playerId;

  @override
  ConsumerState<NewPlayerWizardScreen> createState() => _NewPlayerWizardScreenState();
}

class _NewPlayerWizardScreenState extends ConsumerState<NewPlayerWizardScreen> {
  final _formKey = GlobalKey<FormState>();
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _document = TextEditingController();
  final _birthDate = TextEditingController();
  final _address = TextEditingController();
  final _jersey = TextEditingController();
  final _height = TextEditingController();
  final _weight = TextEditingController();
  final _medicalCoverage = TextEditingController();
  final _allergies = TextEditingController();
  final _medication = TextEditingController();
  final _illnesses = TextEditingController();
  final _restrictions = TextEditingController();
  final _emergency = TextEditingController();
  final _medicalNotes = TextEditingController();
  final _treatmentNotes = TextEditingController();
  final _guardianName = TextEditingController();
  final _guardianDocument = TextEditingController();
  final _guardianPhone = TextEditingController();
  final _guardianEmail = TextEditingController();
  final _guardianAlternate = TextEditingController();

  List<Map<String, dynamic>> _teams = [];
  Map<String, dynamic> _options = {};
  Map<String, dynamic>? _player;
  int? _selectedTeamId;
  int? _categoryId;
  int? _playerId;
  String? _position;
  String? _kitSize;
  String? _preferredFoot;
  String? _nationality;
  String? _bloodType;
  String? _guardianRelationship;
  bool? _vaccinationComplete;
  bool? _ongoingTreatment;
  int _step = 0;
  bool _loading = true;
  bool _saving = false;
  String? _error;
  String? _photoUrl;
  String? _inviteUrl;

  String _teamLabel(Map<String, dynamic> team) {
    final name = team['name'] as String? ?? 'Equipo';
    final category = team['category'] as String? ?? '';
    final tournament = team['tournament_name'] as String? ?? '';
    return [name, category, tournament].where((part) => part.isNotEmpty).join(' · ');
  }

  String? get _selectedTeamName {
    if (_selectedTeamId == null) return null;
    for (final team in _teams) {
      if (team['id'] == _selectedTeamId) return _teamLabel(team);
    }
    return null;
  }

  @override
  void initState() {
    super.initState();
    _selectedTeamId = widget.teamId;
    _categoryId = widget.categoryId;
    _playerId = widget.playerId;
    _load();
  }

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _document.dispose();
    _birthDate.dispose();
    _address.dispose();
    _jersey.dispose();
    _height.dispose();
    _weight.dispose();
    _medicalCoverage.dispose();
    _allergies.dispose();
    _medication.dispose();
    _illnesses.dispose();
    _restrictions.dispose();
    _emergency.dispose();
    _medicalNotes.dispose();
    _treatmentNotes.dispose();
    _guardianName.dispose();
    _guardianDocument.dispose();
    _guardianPhone.dispose();
    _guardianEmail.dispose();
    _guardianAlternate.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repo = ref.read(playerRosterRepositoryProvider);
      final context = await repo.fetchContext();
      final seen = <int>{};
      var teams = (context['teams'] as List<dynamic>? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .where((team) => seen.add(team['id'] as int))
          .toList();
      if (_categoryId != null) {
        teams = teams.where((team) => team['category_id'] == _categoryId).toList();
      }
      final options = Map<String, dynamic>.from(context['options'] as Map? ?? {});

      Map<String, dynamic>? player;
      if (_playerId != null && _categoryId != null) {
        player = await repo.fetchPlayerRoster(categoryId: _categoryId!, playerId: _playerId!);
        _applyPlayer(player);
      } else if (_selectedTeamId != null && teams.isNotEmpty) {
        final team = teams.firstWhere(
          (t) => t['id'] == _selectedTeamId,
          orElse: () => teams.first,
        );
        _categoryId ??= team['category_id'] as int?;
      }

      if (mounted) {
        setState(() {
          _teams = teams;
          _options = options;
          _player = player;
          _nationality ??= ((options['nationalities'] as List<dynamic>? ?? ['Argentina']).first as String?);
          _loading = false;
        });
      }
    } on AppException catch (error) {
      if (mounted) setState(() {
        _loading = false;
        _error = error.message;
      });
    } catch (_) {
      if (mounted) setState(() {
        _loading = false;
        _error = 'No pudimos cargar el formulario de jugador.';
      });
    }
  }

  void _applyPlayer(Map<String, dynamic> player) {
    _playerId = player['id'] as int?;
    _categoryId = player['category_id'] as int? ?? _categoryId;
    _firstName.text = player['first_name'] as String? ?? '';
    _lastName.text = player['last_name'] as String? ?? '';
    _document.text = player['document_number'] as String? ?? '';
    _birthDate.text = player['birth_date'] as String? ?? '';
    _address.text = player['address'] as String? ?? '';
    _jersey.text = '${player['jersey_number'] ?? ''}';
    _height.text = player['height'] as String? ?? '';
    _weight.text = player['weight'] as String? ?? '';
    _position = player['position'] as String?;
    _kitSize = player['kit_size'] as String?;
    _preferredFoot = player['preferred_foot'] as String?;
    _nationality = player['nationality'] as String?;
    _bloodType = player['blood_type'] as String?;
    _medicalCoverage.text = player['medical_coverage'] as String? ?? '';
    _allergies.text = player['allergies'] as String? ?? '';
    _medication.text = player['medication'] as String? ?? '';
    _illnesses.text = player['illnesses'] as String? ?? '';
    _restrictions.text = player['restrictions'] as String? ?? '';
    _emergency.text = player['emergency_contact'] as String? ?? '';
    _medicalNotes.text = player['medical_notes'] as String? ?? '';
    _vaccinationComplete = player['vaccination_calendar_complete'] as bool?;
    _ongoingTreatment = player['ongoing_treatment'] as bool?;
    _treatmentNotes.text = player['ongoing_treatment_notes'] as String? ?? '';
    _photoUrl = player['photo'] as String?;
    _selectedTeamId = (player['team'] as Map?)?['id'] as int?;

    final guardian = player['guardian'] as Map<String, dynamic>?;
    if (guardian != null) {
      _guardianName.text = guardian['name'] as String? ?? '';
      _guardianDocument.text = guardian['document_number'] as String? ?? '';
      _guardianPhone.text = guardian['phone'] as String? ?? '';
      _guardianEmail.text = guardian['email'] as String? ?? '';
      _guardianAlternate.text = guardian['alternate_contact'] as String? ?? '';
      _guardianRelationship = guardian['relationship'] as String?;
    }

    final invitation = player['invitation'] as Map<String, dynamic>?;
    _inviteUrl = invitation?['url'] as String?;
  }

  List<String> _flatKitSizes() {
    final groups = _options['kit_sizes'] as Map<String, dynamic>? ?? {};
    return groups.values.expand((value) => (value as List<dynamic>).map((e) => e.toString())).toList();
  }

  List<String> _stringOptions(String key) {
    return (_options[key] as List<dynamic>? ?? []).map((e) => e.toString()).toList();
  }

  Map<String, dynamic>? _documentFor(String type) {
    final docs = (_player?['documents'] as List<dynamic>? ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    for (final doc in docs) {
      if (doc['type'] == type) return doc;
    }
    return null;
  }

  Future<void> _saveSportsProfile() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedTeamId == null) {
      setState(() => _error = 'Seleccioná el equipo del jugador.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final repo = ref.read(playerRosterRepositoryProvider);
      final payload = {
        'team_id': _selectedTeamId,
        'first_name': _firstName.text.trim(),
        'last_name': _lastName.text.trim(),
        'document_number': _document.text.trim().isEmpty ? null : _document.text.trim(),
        'birth_date': _birthDate.text.trim().isEmpty ? null : _birthDate.text.trim(),
        'nationality': _nationality,
        'address': _address.text.trim().isEmpty ? null : _address.text.trim(),
        'position': _position,
        'jersey_number': int.tryParse(_jersey.text.trim()),
        'kit_size': _kitSize,
        'preferred_foot': _preferredFoot,
        'height': _height.text.trim().isEmpty ? null : _height.text.trim(),
        'weight': _weight.text.trim().isEmpty ? null : _weight.text.trim(),
      };

      Map<String, dynamic> player;
      if (_playerId == null) {
        _categoryId ??= _teams.firstWhere((t) => t['id'] == _selectedTeamId)['category_id'] as int;
        player = await repo.createPlayer(categoryId: _categoryId!, payload: payload);
        _playerId = player['id'] as int?;
      } else {
        player = await repo.updatePlayer(
          categoryId: _categoryId!,
          playerId: _playerId!,
          payload: payload..remove('team_id'),
        );
      }

      if (mounted) {
        setState(() {
          _player = player;
          _photoUrl = player['photo'] as String?;
          _saving = false;
          _step = 1;
        });
      }
    } on AppException catch (error) {
      if (mounted) setState(() {
        _saving = false;
        _error = error.message;
      });
    } catch (_) {
      if (mounted) setState(() {
        _saving = false;
        _error = 'No pudimos guardar el perfil deportivo.';
      });
    }
  }

  Future<void> _pickAndUploadDocument(String type) async {
    if (_playerId == null || _categoryId == null) return;

    final picker = ImagePicker();
    final file = await picker.pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (file == null) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final result = await ref.read(playerRosterRepositoryProvider).uploadDocument(
            categoryId: _categoryId!,
            playerId: _playerId!,
            type: type,
            filePath: file.path,
          );
      final player = result['player'] as Map<String, dynamic>? ?? _player;
      if (mounted) {
        setState(() {
          _player = player;
          _photoUrl = player?['photo'] as String? ?? _photoUrl;
          _saving = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$type cargado.')));
      }
    } on AppException catch (error) {
      if (mounted) setState(() {
        _saving = false;
        _error = error.message;
      });
    } catch (_) {
      if (mounted) setState(() {
        _saving = false;
        _error = 'No pudimos subir el documento.';
      });
    }
  }

  Future<void> _saveMedicalProfile() async {
    if (_playerId == null || _categoryId == null) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final player = await ref.read(playerRosterRepositoryProvider).updatePlayer(
            categoryId: _categoryId!,
            playerId: _playerId!,
            payload: {
              'blood_type': _bloodType,
              'medical_coverage': _medicalCoverage.text.trim().isEmpty ? null : _medicalCoverage.text.trim(),
              'allergies': _allergies.text.trim().isEmpty ? null : _allergies.text.trim(),
              'medication': _medication.text.trim().isEmpty ? null : _medication.text.trim(),
              'illnesses': _illnesses.text.trim().isEmpty ? null : _illnesses.text.trim(),
              'restrictions': _restrictions.text.trim().isEmpty ? null : _restrictions.text.trim(),
              'emergency_contact': _emergency.text.trim().isEmpty ? null : _emergency.text.trim(),
              'medical_notes': _medicalNotes.text.trim().isEmpty ? null : _medicalNotes.text.trim(),
              'vaccination_calendar_complete': _vaccinationComplete,
              'ongoing_treatment': _ongoingTreatment,
              'ongoing_treatment_notes': _treatmentNotes.text.trim().isEmpty ? null : _treatmentNotes.text.trim(),
            },
          );

      if (mounted) {
        setState(() {
          _player = player;
          _saving = false;
          _step = 3;
        });
      }
    } on AppException catch (error) {
      if (mounted) setState(() {
        _saving = false;
        _error = error.message;
      });
    } catch (_) {
      if (mounted) setState(() {
        _saving = false;
        _error = 'No pudimos guardar la ficha médica.';
      });
    }
  }

  Future<void> _saveTutorAndInvite() async {
    if (_playerId == null || _categoryId == null) return;
    if (_guardianName.text.trim().isEmpty) {
      setState(() => _error = 'Indicá el nombre del tutor.');
      return;
    }
    if (_guardianEmail.text.trim().isEmpty && _guardianPhone.text.trim().isEmpty) {
      setState(() => _error = 'Indicá teléfono o email del tutor.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final repo = ref.read(playerRosterRepositoryProvider);
      var player = await repo.updatePlayer(
        categoryId: _categoryId!,
        playerId: _playerId!,
        payload: {
          'guardian_name': _guardianName.text.trim(),
          'guardian_document_number': _guardianDocument.text.trim().isEmpty ? null : _guardianDocument.text.trim(),
          'guardian_relationship': _guardianRelationship,
          'guardian_phone': _guardianPhone.text.trim().isEmpty ? null : _guardianPhone.text.trim(),
          'guardian_email': _guardianEmail.text.trim().isEmpty ? null : _guardianEmail.text.trim(),
          'guardian_alternate_contact': _guardianAlternate.text.trim().isEmpty ? null : _guardianAlternate.text.trim(),
        },
      );

      if (_guardianEmail.text.trim().isNotEmpty) {
        final invite = await repo.inviteGuardian(
          categoryId: _categoryId!,
          playerId: _playerId!,
          email: _guardianEmail.text.trim(),
        );
        player = invite['player'] as Map<String, dynamic>? ?? player;
        _inviteUrl = (invite['invitation'] as Map?)?['url'] as String?;
      }

      if (mounted) {
        setState(() {
          _player = player;
          _saving = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Ficha del jugador completada. El tutor recibirá el enlace de confirmación.')),
        );
        context.go('/players/$_playerId');
      }
    } on AppException catch (error) {
      if (mounted) setState(() {
        _saving = false;
        _error = error.message;
      });
    } catch (_) {
      if (mounted) setState(() {
        _saving = false;
        _error = 'No pudimos confirmar al tutor.';
      });
    }
  }

  Future<void> _copyInviteUrl() async {
    if (_inviteUrl == null || _inviteUrl!.isEmpty) return;
    await Clipboard.setData(ClipboardData(text: _inviteUrl!));
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enlace copiado al portapapeles.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final stepLabels = ['Perfil deportivo', 'Documentación', 'Ficha médica', 'Tutor y confirmación'];

    return Scaffold(
      backgroundColor: StcColors.background,
      body: SafeArea(
        child: _loading
            ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
            : Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(18, 8, 18, 0),
                    child: Row(
                      children: [
                        StcBackButton(onPressed: () {
                          if (_step > 0) {
                            setState(() => _step -= 1);
                          } else {
                            context.pop();
                          }
                        }),
                        const Spacer(),
                        Text(
                          '6.${_step + 2}${String.fromCharCode(65 + _step)}',
                          style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                  Expanded(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
                      child: Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            const Text(
                              'NUEVO JUGADOR',
                              style: TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w900),
                            ),
                            const SizedBox(height: 4),
                            Text(stepLabels[_step], style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w700)),
                            const SizedBox(height: 14),
                            StcStepHeader(
                              step: _step + 1,
                              total: 4,
                              description: switch (_step) {
                                0 => 'Datos deportivos e identidad del jugador.',
                                1 => 'Subí DNI, foto y cobertura médica.',
                                2 => 'Información médica y contacto de emergencia.',
                                _ => 'Datos del tutor y envío de confirmación.',
                              },
                            ),
                            const SizedBox(height: 16),
                            if (_error != null) ...[
                              StcInfoCard(title: 'Revisá los datos', body: _error!),
                              const SizedBox(height: 12),
                            ],
                            if (_step == 0) _buildSportsStep(),
                            if (_step == 1) _buildDocumentsStep(),
                            if (_step == 2) _buildMedicalStep(),
                            if (_step == 3) _buildTutorStep(),
                            const SizedBox(height: 18),
                            StcPrimaryButton(
                              label: _step == 3 ? 'Finalizar y enviar al tutor' : 'Continuar',
                              loading: _saving,
                              onPressed: _saving
                                  ? null
                                  : switch (_step) {
                                      0 => _saveSportsProfile,
                                      1 => () => setState(() => _step = 2),
                                      2 => _saveMedicalProfile,
                                      _ => _saveTutorAndInvite,
                                    },
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
      ),
    );
  }

  Widget _buildSportsStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Center(child: StcPhotoPicker(photoUrl: _photoUrl, onTap: () => _pickAndUploadDocument('Foto del jugador'))),
        const SizedBox(height: 16),
        StcDropdownField(
          label: 'Equipo',
          value: _selectedTeamName,
          items: _teams.map(_teamLabel).toList(),
          onChanged: (value) {
            if (_playerId != null || value == null) return;
            final team = _teams.firstWhere((t) => _teamLabel(t) == value);
            setState(() {
              _selectedTeamId = team['id'] as int?;
              _categoryId = team['category_id'] as int?;
            });
          },
        ),
        const SizedBox(height: 10),
        StcFormRow(children: [
          StcAuthField(label: 'Nombre', controller: _firstName, validator: (v) => (v == null || v.trim().isEmpty) ? 'Requerido' : null),
          StcAuthField(label: 'Apellido', controller: _lastName, validator: (v) => (v == null || v.trim().isEmpty) ? 'Requerido' : null),
        ]),
        const SizedBox(height: 10),
        StcFormRow(children: [
          StcAuthField(label: 'DNI', controller: _document, keyboardType: TextInputType.number),
          StcAuthField(label: 'Nacimiento (AAAA-MM-DD)', controller: _birthDate),
        ]),
        const SizedBox(height: 10),
        StcDropdownField(
          label: 'Nacionalidad',
          value: _nationality,
          items: _stringOptions('nationalities'),
          onChanged: (value) => setState(() => _nationality = value),
        ),
        const SizedBox(height: 10),
        StcAuthField(label: 'Domicilio', controller: _address),
        const SizedBox(height: 10),
        StcDropdownField(
          label: 'Posición',
          value: _position,
          items: _stringOptions('positions'),
          onChanged: (value) => setState(() => _position = value),
        ),
        const SizedBox(height: 10),
        StcFormRow(children: [
          StcAuthField(label: 'Camiseta', controller: _jersey, keyboardType: TextInputType.number),
          StcDropdownField(
            label: 'Talle',
            value: _kitSize,
            items: _flatKitSizes(),
            onChanged: (value) => setState(() => _kitSize = value),
          ),
        ]),
        const SizedBox(height: 10),
        StcFormRow(children: [
          StcDropdownField(
            label: 'Pie hábil',
            value: _preferredFoot,
            items: _stringOptions('preferred_feet'),
            onChanged: (value) => setState(() => _preferredFoot = value),
          ),
          StcAuthField(label: 'Altura (cm)', controller: _height),
        ]),
        const SizedBox(height: 10),
        StcAuthField(label: 'Peso (kg)', controller: _weight),
      ],
    );
  }

  Widget _buildDocumentsStep() {
    final types = _stringOptions('document_types');

    return Column(
      children: types.map((type) {
        final doc = _documentFor(type);
        final uploaded = doc?['file_url'] != null;
        return Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: StcSurfaceCard(
            onTap: _saving ? null : () => _pickAndUploadDocument(type),
            padding: const EdgeInsets.fromLTRB(15, 13, 15, 13),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(type.toUpperCase(), style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 10)),
                      const SizedBox(height: 6),
                      Text(
                        uploaded ? 'Cargado · ${doc?['status_label'] ?? 'Pendiente'}' : 'Tocá para subir imagen',
                        style: const TextStyle(color: StcColors.textBody, fontSize: 11),
                      ),
                    ],
                  ),
                ),
                StcStatusBadge(
                  label: uploaded ? 'OK' : 'PENDIENTE',
                  color: uploaded ? const Color(0xFF2EFF94) : StcColors.gold,
                ),
              ],
            ),
          ),
        );
      }).toList(),
    );
  }

  Widget _buildMedicalStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        StcDropdownField(
          label: 'Grupo sanguíneo',
          value: _bloodType,
          items: _stringOptions('blood_types'),
          onChanged: (value) => setState(() => _bloodType = value),
        ),
        const SizedBox(height: 10),
        StcAuthField(label: 'Cobertura médica', controller: _medicalCoverage),
        const SizedBox(height: 10),
        StcAuthField(label: 'Alergias', controller: _allergies, hint: 'Ninguna conocida'),
        const SizedBox(height: 10),
        StcAuthField(label: 'Medicación habitual', controller: _medication),
        const SizedBox(height: 10),
        StcAuthField(label: 'Enfermedades', controller: _illnesses),
        const SizedBox(height: 10),
        StcAuthField(label: 'Restricciones deportivas', controller: _restrictions),
        const SizedBox(height: 10),
        StcAuthField(label: 'Contacto de emergencia', controller: _emergency),
        const SizedBox(height: 10),
        StcAuthField(label: 'Observaciones médicas', controller: _medicalNotes, hint: 'Información adicional'),
        const SizedBox(height: 10),
        _buildBoolChip('Calendario de vacunas completo', _vaccinationComplete, (value) => setState(() => _vaccinationComplete = value)),
        const SizedBox(height: 8),
        _buildBoolChip('Tratamiento en curso', _ongoingTreatment, (value) => setState(() => _ongoingTreatment = value)),
        if (_ongoingTreatment == true) ...[
          const SizedBox(height: 10),
          StcAuthField(label: 'Detalle del tratamiento', controller: _treatmentNotes),
        ],
      ],
    );
  }

  Widget _buildTutorStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        StcAuthField(label: 'Nombre del tutor', controller: _guardianName),
        const SizedBox(height: 10),
        StcFormRow(children: [
          StcAuthField(label: 'DNI tutor', controller: _guardianDocument),
          StcDropdownField(
            label: 'Parentesco',
            value: _guardianRelationship,
            items: _stringOptions('guardian_relationships'),
            onChanged: (value) => setState(() => _guardianRelationship = value),
          ),
        ]),
        const SizedBox(height: 10),
        StcAuthField(label: 'Teléfono', controller: _guardianPhone, keyboardType: TextInputType.phone),
        const SizedBox(height: 10),
        StcAuthField(label: 'Email (invitación)', controller: _guardianEmail, keyboardType: TextInputType.emailAddress),
        const SizedBox(height: 10),
        StcAuthField(label: 'Contacto alternativo', controller: _guardianAlternate),
        if (_inviteUrl != null && _inviteUrl!.isNotEmpty) ...[
          const SizedBox(height: 16),
          StcInfoCard(
            title: 'ENLACE GENERADO',
            body: _inviteUrl!,
            footer: 'Copialo y compartilo con el tutor por WhatsApp.',
          ),
          const SizedBox(height: 10),
          StcSecondaryButton(label: 'Copiar enlace', onPressed: _copyInviteUrl),
        ],
        const SizedBox(height: 12),
        StcInfoCard(
          title: 'CONFIRMACIÓN LEGAL',
          body: 'Al finalizar se enviará al tutor el enlace para autorizar participación, uso de imagen y aptitud médica.',
        ),
      ],
    );
  }

  Widget _buildBoolChip(String label, bool? value, ValueChanged<bool> onChanged) {
    return Row(
      children: [
        Expanded(child: Text(label, style: const TextStyle(color: StcColors.textBody, fontSize: 11))),
        ChoiceChip(
          label: const Text('Sí', style: TextStyle(fontSize: 10)),
          selected: value == true,
          onSelected: (_) => onChanged(true),
          selectedColor: StcColors.cyan.withValues(alpha: 0.25),
        ),
        const SizedBox(width: 6),
        ChoiceChip(
          label: const Text('No', style: TextStyle(fontSize: 10)),
          selected: value == false,
          onSelected: (_) => onChanged(false),
          selectedColor: StcColors.cyan.withValues(alpha: 0.25),
        ),
      ],
    );
  }
}
