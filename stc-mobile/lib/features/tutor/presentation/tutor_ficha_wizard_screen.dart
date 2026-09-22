import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/errors/app_exception.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_field.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_form_field.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../tutor_providers.dart';

class TutorFichaWizardScreen extends ConsumerStatefulWidget {
  const TutorFichaWizardScreen({super.key, required this.playerId});

  final int playerId;

  @override
  ConsumerState<TutorFichaWizardScreen> createState() => _TutorFichaWizardScreenState();
}

class _TutorFichaWizardScreenState extends ConsumerState<TutorFichaWizardScreen> {
  final _playerFirst = TextEditingController();
  final _playerLast = TextEditingController();
  final _playerDocument = TextEditingController();
  final _playerEmail = TextEditingController();
  final _guardianFirst = TextEditingController();
  final _guardianLast = TextEditingController();
  final _guardianDocument = TextEditingController();
  final _guardianEmail = TextEditingController();
  final _guardianPhone = TextEditingController();
  final _guardianAltName = TextEditingController();
  final _guardianAltPhone = TextEditingController();
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

  Map<String, dynamic> _options = {};
  Map<String, dynamic> _authorizationTexts = {};
  List<String> _uploadTypes = [];
  List<String> _authorizationTypes = [];
  Map<String, String> _documentPaths = {};
  Map<String, String> _existingDocUrls = {};
  final Set<String> _acceptedAuth = {};
  bool _locked = false;
  bool _consent = false;
  bool _loading = true;
  bool _saving = false;
  String? _error;
  int _step = 0;
  String? _relationship;
  String? _nationality;
  String? _kitSize;
  String? _position;
  String? _preferredFoot;
  String? _bloodType;
  bool _vaccinationComplete = true;
  bool? _ongoingTreatment;

  static const _stepLabels = [
    'Tutor y jugador',
    'Datos personales',
    'Deporte',
    'Ficha médica',
    'Documentación',
    'Autorizaciones',
  ];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _playerFirst.dispose();
    _playerLast.dispose();
    _playerDocument.dispose();
    _playerEmail.dispose();
    _guardianFirst.dispose();
    _guardianLast.dispose();
    _guardianDocument.dispose();
    _guardianEmail.dispose();
    _guardianPhone.dispose();
    _guardianAltName.dispose();
    _guardianAltPhone.dispose();
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
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await ref.read(tutorFichaRepositoryProvider).fetchFicha(widget.playerId);
      final player = Map<String, dynamic>.from(data['player'] as Map? ?? {});
      final guardian = Map<String, dynamic>.from(data['guardian'] as Map? ?? {});

      _playerFirst.text = player['first_name'] as String? ?? '';
      _playerLast.text = player['last_name'] as String? ?? '';
      _playerDocument.text = player['document_number'] as String? ?? '';
      _playerEmail.text = player['email'] as String? ?? '';
      _birthDate.text = player['birth_date'] as String? ?? '';
      _address.text = player['address'] as String? ?? '';
      _jersey.text = '${player['jersey_number'] ?? ''}';
      _height.text = player['height'] as String? ?? '';
      _weight.text = player['weight'] as String? ?? '';
      _medicalCoverage.text = player['medical_coverage'] as String? ?? '';
      _allergies.text = player['allergies'] as String? ?? 'Ninguna';
      _medication.text = player['medication'] as String? ?? 'Ninguna';
      _illnesses.text = player['illnesses'] as String? ?? 'Ninguna';
      _restrictions.text = player['restrictions'] as String? ?? 'Ninguna';
      _emergency.text = player['emergency_contact'] as String? ?? '';
      _medicalNotes.text = player['medical_notes'] as String? ?? '';
      _treatmentNotes.text = player['ongoing_treatment_notes'] as String? ?? '';

      _guardianFirst.text = guardian['first_name'] as String? ?? '';
      _guardianLast.text = guardian['last_name'] as String? ?? '';
      _guardianDocument.text = guardian['document_number'] as String? ?? '';
      _guardianEmail.text = guardian['email'] as String? ?? '';
      _guardianPhone.text = guardian['phone'] as String? ?? '';
      final alternate = guardian['alternate_contact'] as String? ?? '';
      if (alternate.contains('·')) {
        final parts = alternate.split('·');
        _guardianAltName.text = parts.first.trim();
        if (parts.length > 1) _guardianAltPhone.text = parts[1].trim();
      }

      _relationship = guardian['relationship'] as String? ?? 'Madre';
      _nationality = player['nationality'] as String? ?? 'Argentina';
      _kitSize = player['kit_size'] as String?;
      _position = player['position'] as String?;
      _preferredFoot = player['preferred_foot'] as String?;
      _bloodType = player['blood_type'] as String?;
      _vaccinationComplete = player['vaccination_calendar_complete'] as bool? ?? true;
      _ongoingTreatment = player['ongoing_treatment'] as bool?;

      _options = Map<String, dynamic>.from(data['options'] as Map? ?? {});
      _authorizationTexts = Map<String, dynamic>.from(data['authorization_texts'] as Map? ?? {});
      _uploadTypes = (data['upload_document_types'] as List<dynamic>? ?? []).map((e) => e.toString()).toList();
      _authorizationTypes = (data['authorization_types'] as List<dynamic>? ?? []).map((e) => e.toString()).toList();
      _existingDocUrls = {
        for (final doc in (data['documents'] as List<dynamic>? ?? []))
          if ((doc as Map)['file_url'] != null) doc['type'] as String: doc['file_url'] as String,
      };
      _locked = data['locked'] as bool? ?? false;

      if (mounted) setState(() => _loading = false);
    } catch (error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = error.toString();
        });
      }
    }
  }

  Future<void> _pickDocument(String type) async {
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (picked == null) return;
    setState(() => _documentPaths[type] = picked.path);
  }

  Future<void> _submit() async {
    if (!_consent) {
      setState(() => _error = 'Confirmá que los datos son correctos.');
      return;
    }
    if (_acceptedAuth.length < _authorizationTypes.length) {
      setState(() => _error = 'Tenés que aceptar las tres autorizaciones legales.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final fields = <String, dynamic>{
        'first_name': _playerFirst.text.trim(),
        'last_name': _playerLast.text.trim(),
        'guardian_first_name': _guardianFirst.text.trim(),
        'guardian_last_name': _guardianLast.text.trim(),
        'guardian_document_number': _guardianDocument.text.trim(),
        'relationship': _relationship ?? 'Madre',
        'email': _guardianEmail.text.trim(),
        'phone': _guardianPhone.text.trim(),
        'guardian_alternate_name': _guardianAltName.text.trim(),
        'guardian_alternate_phone': _guardianAltPhone.text.trim(),
        'document_number': _playerDocument.text.trim(),
        'birth_date': _birthDate.text.trim(),
        'nationality': _nationality ?? 'Argentina',
        'address': _address.text.trim(),
        'kit_size': _kitSize ?? 'M',
        'jersey_number': _jersey.text.trim().isEmpty ? null : int.tryParse(_jersey.text.trim()),
        'position': _position,
        'preferred_foot': _preferredFoot,
        'height': _height.text.trim(),
        'weight': _weight.text.trim(),
        'blood_type': _bloodType ?? 'O+',
        'medical_coverage': _medicalCoverage.text.trim(),
        'allergies': _allergies.text.trim(),
        'medication': _medication.text.trim(),
        'illnesses': _illnesses.text.trim(),
        'restrictions': _restrictions.text.trim(),
        'emergency_contact': _emergency.text.trim(),
        'medical_notes': _medicalNotes.text.trim(),
        'vaccination_calendar_complete': _vaccinationComplete ? '1' : '0',
        'consent': '1',
        'complete': '1',
        'player_email': _playerEmail.text.trim().isEmpty ? null : _playerEmail.text.trim(),
      };

      if (_ongoingTreatment != null) {
        fields['ongoing_treatment'] = _ongoingTreatment! ? '1' : '0';
        if (_ongoingTreatment == true) {
          fields['ongoing_treatment_notes'] = _treatmentNotes.text.trim();
        }
      }

      for (var i = 0; i < _authorizationTypes.length; i++) {
        fields['auth[$i]'] = _authorizationTypes[i];
      }

      final result = await ref.read(tutorFichaRepositoryProvider).submitFicha(
            playerId: widget.playerId,
            fields: fields,
            documentPaths: _documentPaths,
          );

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(result['message'] as String? ?? 'Ficha enviada correctamente.')),
        );
        context.go('/tutor/players/${widget.playerId}');
      }
    } on AppException catch (error) {
      setState(() => _error = error.message);
    } catch (error) {
      setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator(color: StcColors.cyan)));
    }

    if (_locked) {
      return Scaffold(
        backgroundColor: StcColors.background,
        body: StcPublicBackground(
          child: SafeArea(
            child: Column(
              children: [
                StcPublicHeader(title: 'FICHA ENVIADA', onBack: () => context.pop()),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: const StcInfoCard(
                      title: 'Ficha en revisión',
                      body: 'Esta ficha ya fue enviada. Si necesitás cambiar algo, contactá al delegado o administrador del torneo.',
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: StcColors.background,
      body: StcAuthScaffold(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            StcPublicHeader(title: 'COMPLETAR FICHA', onBack: () => context.pop()),
            StcStepHeader(step: _step + 1, total: _stepLabels.length, description: _stepLabels[_step]),
            const SizedBox(height: 12),
            Expanded(child: SingleChildScrollView(child: _buildStep())),
            if (_error != null) ...[
              const SizedBox(height: 8),
              Text(_error!, style: const TextStyle(color: Colors.redAccent)),
            ],
            const SizedBox(height: 12),
            Row(
              children: [
                if (_step > 0)
                  Expanded(
                    child: StcSecondaryButton(label: 'Anterior', onPressed: () => setState(() => _step -= 1)),
                  ),
                if (_step > 0) const SizedBox(width: 10),
                Expanded(
                  child: StcPrimaryButton(
                    label: _step == _stepLabels.length - 1 ? 'Enviar ficha' : 'Siguiente',
                    loading: _saving,
                    onPressed: _step == _stepLabels.length - 1 ? _submit : () => setState(() => _step += 1),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStep() {
    return switch (_step) {
      0 => _identityStep(),
      1 => _personalStep(),
      2 => _sportStep(),
      3 => _medicalStep(),
      4 => _documentsStep(),
      _ => _legalStep(),
    };
  }

  Widget _identityStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('DATOS DEL JUGADOR', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
        const SizedBox(height: 10),
        StcFormRow(children: [
          StcAuthField(label: 'Nombre', controller: _playerFirst),
          StcAuthField(label: 'Apellido', controller: _playerLast),
        ]),
        const SizedBox(height: 10),
        StcAuthField(label: 'DNI jugador', controller: _playerDocument),
        const SizedBox(height: 18),
        const Text('DATOS DEL TUTOR', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
        const SizedBox(height: 10),
        StcFormRow(children: [
          StcAuthField(label: 'Nombre tutor', controller: _guardianFirst),
          StcAuthField(label: 'Apellido tutor', controller: _guardianLast),
        ]),
        const SizedBox(height: 10),
        StcAuthField(label: 'DNI tutor', controller: _guardianDocument),
        const SizedBox(height: 10),
        StcDropdownField(
          label: 'Parentesco',
          value: _relationship,
          items: (_options['guardian_relationships'] as List<dynamic>? ?? []).map((e) => e.toString()).toList(),
          onChanged: (value) => setState(() => _relationship = value),
        ),
        const SizedBox(height: 10),
        StcAuthField(label: 'Email tutor', controller: _guardianEmail, keyboardType: TextInputType.emailAddress),
        const SizedBox(height: 10),
        StcAuthField(label: 'Teléfono', controller: _guardianPhone, keyboardType: TextInputType.phone),
        const SizedBox(height: 10),
        StcFormRow(children: [
          StcAuthField(label: 'Contacto alternativo', controller: _guardianAltName),
          StcAuthField(label: 'Tel. alternativo', controller: _guardianAltPhone, keyboardType: TextInputType.phone),
        ]),
      ],
    );
  }

  Widget _personalStep() {
    return Column(
      children: [
        StcAuthField(label: 'Fecha de nacimiento (AAAA-MM-DD)', controller: _birthDate),
        const SizedBox(height: 10),
        StcDropdownField(
          label: 'Nacionalidad',
          value: _nationality,
          items: (_options['nationalities'] as List<dynamic>? ?? []).map((e) => e.toString()).toList(),
          onChanged: (value) => setState(() => _nationality = value),
        ),
        const SizedBox(height: 10),
        StcAuthField(label: 'Domicilio', controller: _address),
        const SizedBox(height: 10),
        StcAuthField(
          label: 'Email del jugador (opcional)',
          controller: _playerEmail,
          keyboardType: TextInputType.emailAddress,
          hint: 'Para acceso al portal del jugador',
        ),
      ],
    );
  }

  Widget _sportStep() {
    return Column(
      children: [
        StcDropdownField(
          label: 'Talle de camiseta',
          value: _kitSize,
          items: (_options['kit_sizes'] as List<dynamic>? ?? []).map((e) => e.toString()).toList(),
          onChanged: (value) => setState(() => _kitSize = value),
        ),
        const SizedBox(height: 10),
        StcAuthField(label: 'Número de camiseta', controller: _jersey, keyboardType: TextInputType.number),
        const SizedBox(height: 10),
        StcDropdownField(
          label: 'Posición',
          value: _position,
          items: (_options['positions'] as List<dynamic>? ?? []).map((e) => e.toString()).toList(),
          onChanged: (value) => setState(() => _position = value),
        ),
        const SizedBox(height: 10),
        StcDropdownField(
          label: 'Pie hábil',
          value: _preferredFoot,
          items: (_options['preferred_feet'] as List<dynamic>? ?? []).map((e) => e.toString()).toList(),
          onChanged: (value) => setState(() => _preferredFoot = value),
        ),
        const SizedBox(height: 10),
        StcFormRow(children: [
          StcAuthField(label: 'Altura (m)', controller: _height),
          StcAuthField(label: 'Peso (kg)', controller: _weight),
        ]),
      ],
    );
  }

  Widget _medicalStep() {
    return Column(
      children: [
        StcDropdownField(
          label: 'Grupo sanguíneo',
          value: _bloodType,
          items: (_options['blood_types'] as List<dynamic>? ?? []).map((e) => e.toString()).toList(),
          onChanged: (value) => setState(() => _bloodType = value),
        ),
        const SizedBox(height: 10),
        StcAuthField(label: 'Cobertura médica', controller: _medicalCoverage),
        const SizedBox(height: 10),
        StcAuthField(label: 'Alergias', controller: _allergies),
        const SizedBox(height: 10),
        StcAuthField(label: 'Medicación', controller: _medication),
        const SizedBox(height: 10),
        StcAuthField(label: 'Enfermedades', controller: _illnesses),
        const SizedBox(height: 10),
        StcAuthField(label: 'Restricciones', controller: _restrictions),
        const SizedBox(height: 10),
        StcAuthField(label: 'Contacto de emergencia', controller: _emergency),
        const SizedBox(height: 10),
        StcAuthField(label: 'Notas médicas', controller: _medicalNotes),
        const SizedBox(height: 10),
        SwitchListTile(
          value: _vaccinationComplete,
          onChanged: (value) => setState(() => _vaccinationComplete = value),
          title: const Text('Calendario de vacunación completo', style: TextStyle(color: Colors.white)),
        ),
      ],
    );
  }

  Widget _documentsStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const StcInfoCard(
          title: 'Documentación obligatoria',
          body: 'Subí DNI frente, DNI dorso y foto del jugador. Podés reemplazar archivos ya cargados.',
        ),
        const SizedBox(height: 12),
        ..._uploadTypes.map((type) {
          final localPath = _documentPaths[type];
          final existing = _existingDocUrls[type];
          return Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: StcSurfaceCard(
              onTap: () => _pickDocument(type),
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  StcPhotoPicker(
                    photoUrl: localPath == null ? existing : null,
                    placeholder: type.split(' ').first.toUpperCase(),
                    square: true,
                    onTap: () => _pickDocument(type),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(type, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                        Text(
                          localPath != null ? 'Archivo seleccionado' : (existing != null ? 'Ya cargado' : 'Pendiente'),
                          style: const TextStyle(color: StcColors.textMuted, fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          );
        }),
      ],
    );
  }

  Widget _legalStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ..._authorizationTypes.map((type) {
          final text = _authorizationTexts[type] as String? ?? '';
          final accepted = _acceptedAuth.contains(type);
          return Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: StcColors.card,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: accepted ? StcColors.cyan : StcColors.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(type.toUpperCase(), style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
                  const SizedBox(height: 8),
                  Text(text, style: const TextStyle(color: StcColors.textMuted, fontSize: 11, height: 1.4)),
                  const SizedBox(height: 8),
                  CheckboxListTile(
                    value: accepted,
                    onChanged: (value) {
                      setState(() {
                        if (value == true) {
                          _acceptedAuth.add(type);
                        } else {
                          _acceptedAuth.remove(type);
                        }
                      });
                    },
                    title: Text('Acepto $type', style: const TextStyle(color: Colors.white, fontSize: 13)),
                    controlAffinity: ListTileControlAffinity.leading,
                    activeColor: StcColors.cyan,
                  ),
                ],
              ),
            ),
          );
        }),
        CheckboxListTile(
          value: _consent,
          onChanged: (value) => setState(() => _consent = value ?? false),
          title: const Text('Confirmo que los datos son correctos', style: TextStyle(color: Colors.white)),
          controlAffinity: ListTileControlAffinity.leading,
          activeColor: StcColors.cyan,
        ),
      ],
    );
  }
}
